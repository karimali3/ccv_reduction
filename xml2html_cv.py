from pprint import pprint
import xml.etree.ElementTree as ET
from yattag import Doc
import jinja2
from datetime import datetime
from itertools import groupby, count
import argparse
import weasyprint
import pathlib

parser = argparse.ArgumentParser("xm2html_cv")
parser.add_argument("--input")
args = parser.parse_args()

in_file = 'RG-Generic-CCV-2023.12.12.xml'
if args.input:
	in_file = args.input

print(in_file)

tree = ET.parse(in_file)
root = tree.getroot()

personal_info = {'addr': {}, 'phone': {}, 'email': {}, 'website': {}}
degrees = {}
recognitions = {}
employment = {}
affiliations = {}
research_funding = {}
courses = {}
course_dev = {}
students = {
	'bsc': {},
	'msc': {},
	'phd': {},
	'postdoc': {},
	'dipl': {},
	'ra': {},
	'unspec': {},
}
staff = {}
editorial = {}

def parse_section(item):
	match item.tag:
		case 'section':
			res = {}
			for child in item:
				if 'label' in child.attrib:
					label = child.attrib['label']
					if label in res:
						if not isinstance(res[label], list):
							res[label] = [res[label]]
						res[label].append(parse_section(child))
					else:
						res[label] = parse_section(child)
			return res
		case 'field':
			if len(item):
				match item[0].tag:
					case 'value':
						return item[0].text
					case 'lov':
						return item[0].text
					case 'refTable':
						return parse_section(item[0])
			return None
		case 'refTable':
			res = {}
			for child in item:
				res[child.attrib['label']] = child.attrib['value']
			return res

sections = {}
for item in root.findall('.//section'):
	label = item.attrib['label']
	num_items = len(root.findall('.//section/[@label="' + label + '"]'))
	if num_items > 1:
		if label not in sections:
			sections[label] = []
		sections[label].append(parse_section(item))
	else:
		sections[label] = parse_section(item)

emp = sections['Employment']['Academic Work Experience'] + sections['Employment']['Non-academic Work Experience']
emp.sort(key=lambda x: datetime.strptime(x['End Date'] or "2999/1", '%Y/%m'), reverse=True)

courses = {}
for item in sections['Courses Taught']:
	if item['Course Title'] not in courses:
		courses[item['Course Title']] = []
	start_date = datetime.strptime(item['Start Date'], '%Y-%m-%d').year
	end_date = datetime.strptime(item['End Date'], '%Y-%m-%d').year
	for y in range(start_date, end_date+1):
		courses[item['Course Title']].append(y)

for key, value in courses.items():
	tmp2 = list(set(value))
	tmp2.sort()
	groups = groupby(tmp2, key=lambda item, c=count(): item-next(c))
	tmp = [list(g) for k, g in groups]
	courses[key] = ', '.join([str(x[0]) if len(x) == 1 else "{}-{}".format(x[0],x[-1]) for x in tmp])


students = {}
for item in sections['Student/Postdoctoral Supervision']:
	if 'Degree Type or Postdoctoral Status' in item and item['Degree Type or Postdoctoral Status'] is not None: 
		student_type = item['Degree Type or Postdoctoral Status']
	else:
		student_type = 'Level Not Specified'

	if student_type not in students:
		students[student_type] = []
	students[student_type].append(item)

sections['Other Memberships'] = [sections['Other Memberships']]
sections['Publications']['Encyclopedia Entries'] = [sections['Publications']['Encyclopedia Entries']]

#pprint(sections['Publications']['Journal Articles'])

env = jinja2.Environment(loader=jinja2.FileSystemLoader('.'))
env.trim_blocks = True
env.lstrip_blocks = True
tmpl = env.get_template('cv_tmpl.html')
content = tmpl.render(sections=sections, emp=emp, courses=courses, students=students)

with open('output.html', mode='w', encoding='utf-8') as fp:
	fp.write(content)

font_config = weasyprint.text.fonts.FontConfiguration()
html = weasyprint.HTML('output.html')
css = weasyprint.CSS(string='''
		@page {
			size: letter;
			margin: 0.5in;
		}
		body {
			font-size: 9px;
		}
		''')
out_name = pathlib.Path(in_file).stem
html.write_pdf(f'{out_name}.pdf', stylesheets=[css], font_config=font_config)
# for child in root:
# 	label = child.attrib['label']
# 	print(child.tag, child.attrib['label'])
# 	if label == 'Personal Information':
# 		for child2 in child:
# 			print('  ', child2.tag, child2.attrib['label'])
# 			primary = 'primaryIndicator' in child2.attrib and child2.attrib['primaryIndicator']
# 			if child2.tag == "section" and child2.attrib['label'] == "Identification":
# 				for child3 in child2:
# 					print('    ', child3.tag, child3.attrib['label'])
# 					if child3.tag == "field":
# 						if len(list(child3)):
# 							personal_info[child3.attrib['label']] = child3[0].text
# 						else:
# 							personal_info[child3.attrib['label']] = None
# 					elif child3.tag == 'section':
# 						if child3.attrib['label'] not in personal_info:
# 							personal_info[child3.attrib['label']] = []
# 						personal_info[child3.attrib['label']].append(child3[0][0].text)
# 			elif child2.attrib['label'] == "Address":
# 				addr_type = ''
# 				addr = []
# 				for child3 in child2:
# 					if child3.attrib['label'] == 'Address Type':
# 						addr_type = child3[0].text
# 						if primary:
# 							addr_type += " (*)"
# 					elif child3.attrib['label'] == 'Location':
# 						country = child3[0][0].attrib['value']
# 						prov = child3[0][1].attrib['value']
# 						addr.append(prov)
# 						addr.append(country)
# 					else:
# 						addr.append(child3[0].text)
# 				personal_info['addr'][addr_type] = addr
# 			elif child2.attrib['label'] == "Telephone":
# 				ph_type = ''
# 				cc = ''
# 				ac = ''
# 				ph = ''
# 				for child3 in child2:
# 					print('      ', child3.tag, child3.attrib['label'])
# 					match child3.attrib['label']:
# 						case 'Phone Type':
# 							ph_type = child3[0].text
# 							if primary:
# 								ph_type += " (*)"
# 						case 'Country Code':
# 							cc = child3[0].text
# 						case 'Area Code':
# 							ac = child3[0].text
# 						case 'Telephone Number':
# 							ph = child3[0].text
# 				personal_info['phone'][ph_type] = f'{cc}-{ac}-{ph}'
# 			elif child2.attrib['label'] == "Email":
# 				email_type = ''
# 				email_addr = ''
# 				match child3.attrib['label']:
# 					case 'Email Type':
# 						email_type = child3[0].text
# 						if primary:
# 							email_type += " (*)"
# 					case 'Email Address':
# 						email_addr = child3[0].text
# 				personal_info['email'][email_type] = email_addr
# 			elif child2.attrib['label'] == 'Website':
# 				web_type = ''
# 				web_addr = ''
# 				match child3.attrib['label']:
# 					case 'Website Type':
# 						web_type = child3[0].text
# 					case 'URL':
# 						web_addr = child3[0].text
# 				personal_info['website'][web_type] = web_addr
# 	elif label == 'Education':
# 		for child2 in child:
# 			print('    ', child2.tag, child2.attrib['label'])
# 			deg = {}
# 			for child3 in child2:
# 				match child3.attrib['label']:
# 					case 'Degree Start Date':
# 						deg['start'] = child3[0].text
# 					case 'Degree Received Date':
# 						deg['end'] = child3[0].text
# 					case 'Dgree Type':
# 						deg['type'] = child3[0].text
# 					case 'Degree Name':
# 						deg['name'] = child3[0].text
# 					case 'Specialization':
# 						deg['spec'] = child3[0].text
# 					case 'Organization':
# 						for child4 in child3[0]:
# 							if child4.attrib['label'] == 'Organization':
# 								deg['inst'] = child4.attrib['value']
# 								break
# 					case 'Degree Status':
# 						pass

							