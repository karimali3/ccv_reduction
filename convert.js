(function (root, factory) {
  if (typeof module === "object" && module.exports) {
    module.exports = factory();
  } else {
    root.CcvConvert = factory();
  }
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  function asList(value) {
    if (value == null) return [];
    return Array.isArray(value) ? value : [value];
  }

  function elementChildren(el) {
    const children = [];
    for (let child = el.firstElementChild; child; child = child.nextElementSibling) {
      children.push(child);
    }
    return children;
  }

  // Match ElementTree's .text: text before the first child element, or null when empty.
  function elementText(el) {
    let text = "";
    for (let i = 0; i < el.childNodes.length; i++) {
      const node = el.childNodes[i];
      if (node.nodeType === 1) break;
      if (node.nodeType === 3 || node.nodeType === 4) text += node.nodeValue;
    }
    return text === "" ? null : text;
  }

  function tagName(el) {
    return el.localName || el.tagName;
  }

  function parseSection(item) {
    const tag = tagName(item);
    if (tag === "section") {
      const res = {};
      for (const child of elementChildren(item)) {
        if (!child.hasAttribute("label")) continue;
        const label = child.getAttribute("label");
        const parsed = parseSection(child);
        if (Object.prototype.hasOwnProperty.call(res, label)) {
          if (!Array.isArray(res[label])) res[label] = [res[label]];
          res[label].push(parsed);
        } else {
          res[label] = parsed;
        }
      }
      return res;
    }
    if (tag === "field") {
      const first = item.firstElementChild;
      if (!first) return null;
      const childTag = tagName(first);
      if (childTag === "value" || childTag === "lov") return elementText(first);
      if (childTag === "refTable") return parseSection(first);
      return null;
    }
    if (tag === "refTable") {
      const res = {};
      for (const child of elementChildren(item)) {
        res[child.getAttribute("label")] = child.getAttribute("value");
      }
      return res;
    }
    return null;
  }

  function buildSections(doc) {
    const all = Array.from(doc.getElementsByTagName("section"));
    const counts = new Map();
    for (const el of all) {
      const label = el.getAttribute("label");
      counts.set(label, (counts.get(label) || 0) + 1);
    }
    const sections = {};
    for (const el of all) {
      const label = el.getAttribute("label");
      const parsed = parseSection(el);
      if (counts.get(label) > 1) {
        if (!Object.prototype.hasOwnProperty.call(sections, label)) sections[label] = [];
        sections[label].push(parsed);
      } else {
        sections[label] = parsed;
      }
    }
    return sections;
  }

  // Year/month comparable to datetime.strptime(value, "%Y/%m"), missing dates last.
  function endKey(item) {
    const raw = item["End Date"] || "2999/1";
    const parts = String(raw).split("/");
    const year = Number.parseInt(parts[0], 10);
    const month = Number.parseInt(parts[1], 10);
    return year * 12 + month;
  }

  function collapseYears(years) {
    const sorted = Array.from(new Set(years)).sort((a, b) => a - b);
    const groups = [];
    let current = [];
    for (const year of sorted) {
      if (current.length === 0 || year === current[current.length - 1] + 1) {
        current.push(year);
      } else {
        groups.push(current);
        current = [year];
      }
    }
    if (current.length) groups.push(current);
    return groups
      .map((group) => (group.length === 1 ? String(group[0]) : group[0] + "-" + group[group.length - 1]))
      .join(", ");
  }

  function yearOf(isoDate) {
    return Number.parseInt(String(isoDate).split("-")[0], 10);
  }

  // Nunjucks has no dict.items(); this keeps the Jinja template working.
  function addItems(value, seen) {
    if (!value || typeof value !== "object") return;
    if (seen.has(value)) return;
    seen.add(value);
    if (Array.isArray(value)) {
      for (const entry of value) addItems(entry, seen);
      return;
    }
    if (typeof value.items !== "function") {
      Object.defineProperty(value, "items", {
        enumerable: false,
        value: function () {
          return Object.keys(this).map((key) => [key, this[key]]);
        },
      });
    }
    for (const key of Object.keys(value)) addItems(value[key], seen);
  }

  function convertCcv(xmlText, Parser) {
    const DOMParserImpl = Parser || globalThis.DOMParser;
    const doc = new DOMParserImpl().parseFromString(xmlText, "text/xml");
    if (doc.getElementsByTagName("parsererror").length) {
      throw new Error("Could not parse the XML file.");
    }

    const sections = buildSections(doc);
    const employment = sections["Employment"] || {};
    const emp = asList(employment["Academic Work Experience"]).concat(
      asList(employment["Non-academic Work Experience"])
    );
    emp.sort((a, b) => endKey(b) - endKey(a));

    const courses = {};
    for (const item of asList(sections["Courses Taught"])) {
      const title = item["Course Title"];
      if (!Object.prototype.hasOwnProperty.call(courses, title)) courses[title] = [];
      const start = yearOf(item["Start Date"]);
      const end = yearOf(item["End Date"]);
      for (let year = start; year <= end; year++) courses[title].push(year);
    }
    for (const title of Object.keys(courses)) {
      courses[title] = collapseYears(courses[title]);
    }

    const students = {};
    for (const item of asList(sections["Student/Postdoctoral Supervision"])) {
      let studentType = "Level Not Specified";
      if (
        Object.prototype.hasOwnProperty.call(item, "Degree Type or Postdoctoral Status") &&
        item["Degree Type or Postdoctoral Status"] != null
      ) {
        studentType = item["Degree Type or Postdoctoral Status"];
      }
      if (!Object.prototype.hasOwnProperty.call(students, studentType)) students[studentType] = [];
      students[studentType].push(item);
    }

    if (sections["Other Memberships"] != null && !Array.isArray(sections["Other Memberships"])) {
      sections["Other Memberships"] = [sections["Other Memberships"]];
    }
    // A single phone is one object. The template loops this list; Nunjucks does not iterate a plain object, which leaves an empty table.
    const personal = sections["Personal Information"];
    if (personal && personal["Telephone"] != null && !Array.isArray(personal["Telephone"])) {
      personal["Telephone"] = [personal["Telephone"]];
    }
    const publications = sections["Publications"];
    if (
      publications &&
      publications["Encyclopedia Entries"] != null &&
      !Array.isArray(publications["Encyclopedia Entries"])
    ) {
      publications["Encyclopedia Entries"] = [publications["Encyclopedia Entries"]];
    }

    const seen = new WeakSet();
    addItems(sections, seen);
    addItems(emp, seen);
    addItems(courses, seen);
    addItems(students, seen);

    return { sections: sections, emp: emp, courses: courses, students: students };
  }

  const PRINT_CSS =
    "@page { size: letter; margin: 0.5in; }";

  function renderCv(nunjucksLib, template, data) {
    const env = new nunjucksLib.Environment(null, {
      autoescape: false,
      trimBlocks: true,
      lstripBlocks: true,
    });
    env.addFilter("commas", function (val) {
      const n = Math.trunc(Number(val));
      if (!Number.isFinite(n)) return "";
      return n.toLocaleString("en-US");
    });
    // Jinja prints None as the text "None"; Nunjucks prints nothing.
    env.addFilter("nonestr", function (val) {
      return val === null ? "None" : val;
    });
    let adapted = template.replace(
      /\{\{\s*'\{:,\}'\.format\((.+?)\)\s*\}\}/g,
      "{{ ($1) | commas }}"
    );
    adapted = adapted.replace(/\{\{([\s\S]*?)\}\}/g, function (_, inner) {
      return "{{ (" + inner + ") | nonestr }}";
    });
    return env.renderString(adapted, data);
  }

  function withPrintCss(html) {
    const style = "<style>" + PRINT_CSS + "</style>";
    if (html.indexOf("</head>") !== -1) return html.replace("</head>", style + "</head>");
    return style + html;
  }

  return {
    convertCcv: convertCcv,
    renderCv: renderCv,
    withPrintCss: withPrintCss,
  };
});
