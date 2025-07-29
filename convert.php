<?php
    require __DIR__ . '/vendor/autoload.php';
    function parse_section($item) {
        $tag = $item->getName();
        // var_dump($tag);
        // print('<br />');
        switch($tag) {
            case 'section':
                $res = array();
                foreach($item->children() as $child) {
                    if(isset($child['label'])) {
                        $label = (string) $child['label'];
                        if(array_key_exists($label, $res)) {
                            if(!array_key_exists(0, $res[$label])) {
                                $tmp = $res[$label];
                                $res[$label] = array();
                                $res[$label][0] = $tmp;
                            }
                            array_push($res[$label], parse_section($child));
                        }
                        else {
                            $res[$label] = parse_section($child);
                        }
                    }
                }
                return $res;
            case 'field':
                foreach($item->children() as $child) {
                    switch($child->getName()) {
                        case 'value':
                            return (string)$child;
                        case 'lov':
                            return (string)$child;
                        case 'refTable':
                            return parse_section($child);
                    }
                }
                return null;
            case 'refTable':
                $res = array();
                foreach($item->children() as $child) {
                    $res[(string)$child['label']] = (string)$child['value'];
                }
                return $res;
        }
        return $tag;
    }

    $input = $_FILES['ccv'];
    $res = 0;
    $out1 = '';

    $xml = simplexml_load_file($input['tmp_name']);
    $sections = array();
    $elems = $xml->xpath('.//section');

    foreach($elems as $item) {
        $label = (string) $item['label'];
        $exp = ".//section[@label='$label']";
        $num = count($xml->xpath($exp));
        if($num > 1) {
            if(!array_key_exists($label, $sections)) {
                $sections[$label] = array();
            }
            array_push($sections[$label], parse_section($item));
        }
        else {
            $sections[$label] = parse_section($item);
        }
    }

    $emp1 = $sections['Employment']['Academic Work Experience'];
    $emp2 = $sections['Employment']['Non-academic Work Experience'];
    $emp = array_merge($emp1, $emp2);
    usort($emp, function ($a, $b) {
        $a_date = DateTime::createFromFormat('Y/n', $a['End Date'] ? $a['End Date'] : '2999/1');
        $b_date = DateTime::createFromFormat('Y/n', $b['End Date'] ? $b['End Date'] : '2999/1');
        return $a_date->getTimestamp() <=> $b_date->getTimestamp();
    });
    $emp = array_reverse($emp);

    $courses = array();
    foreach($sections['Courses Taught'] as $item) {
        if(!array_key_exists($item['Course Title'], $courses)) {
            $courses[$item['Course Title']] = array();
        }
        $start = date_parse_from_format('Y-m-d', $item['Start Date'])['year'];
        $end = date_parse_from_format('Y-m-d', $item['End Date'])['year'];
        for($i = $start; $i <= $end; $i++) {
            array_push($courses[$item['Course Title']], $i);
        }
    }

    foreach($courses as $key => $value) {
        $tmp = array_unique($value);
        sort($tmp);
        $out = array();
        $tmp2 = array();
        foreach($tmp as $item) {
            if(empty($tmp2)) {
                array_push($tmp2, $item);
            }
            elseif(in_array($item-1, $tmp2)) {
                array_push($tmp2, $item);
            }
            else {
                array_push($out, $tmp2);
                $tmp2 = array();
                array_push($tmp2, $item);
            }
        }
        array_push($out, $tmp2);
        $year_str = join(', ', array_map(function ($item) {
            if(count($item) == 1) {
                return $item[0];
            }
            else {
                return $item[0] . '-' . $item[count($item)-1];
            }
        }, $out));
        $courses[$key] = $year_str;
    }

    $students = array();
    foreach($sections['Student/Postdoctoral Supervision'] as $item) {
        $student_type = '';
        if(isset($item['Degree Type or Postdoctoral Status']) && !empty($item['Degree Type or Postdoctoral Status'])) {
            $student_type = $item['Degree Type or Postdoctoral Status'];
        }
        else {
            $student_type = 'Level Not Specified';
        }

        if(!in_array($student_type, $students)) {
            $students[$student_type] = array();
        }
        array_push($students[$student_type], $item);
    }

    var_dump($students);
    die();

    if(!array_key_exists(0, $sections['Other Memberships'])) {
        $sections['Other Memberships'] = [$sections['Other Memberships']];
    }

    if(!array_key_exists(0, $sections['Other Memberships'])) {
        $sections['Publications']['Encyclopedia Entries'] = [$sections['Publications']['Encyclopedia Entries']];
    }

    // $output = exec("python3 xml2html_cv.py --input " . $input['tmp_name'], $out1, $res);
    // if($res != 0) {
    //     print("Error:");
    //     echo("<br />");
    //     var_dump($input);
    //     var_dump($out1);
    //     var_dump($output);
    //     die();
    // }
    $base = pathinfo($input['tmp_name'], PATHINFO_FILENAME);
    $out = pathinfo($input['name'], PATHINFO_FILENAME);
    rename("$base.pdf", "$out.pdf");
    if(file_exists("$out.pdf")) {
        print("$out.pdf");
    }
?>
<?php $pi = $sections['Personal Information']; ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title><?php echo $pi['Identification']['Title'] ?> <?php echo $pi['Identification']['First Name'] ?> <?php echo $pi['Identification']['Family Name'] ?></title>
    <style>
        body {
            font-family: sans-serif;
        }
        td {
            vertical-align: top;
            padding-right: 20px;
            padding-bottom: 5px;
        }
        h1 {
            color: darkcyan;
        }
    </style>
</head>
<body>
    <h1><?php echo $pi['Identification']['Title'] ?> <?php echo $pi['Identification']['First Name'] ?> <?php echo $pi['Identification']['Family Name'] ?></h1>
    <?php
    foreach($pi['Identification'] as $key => $value) {
        if(!in_array($key, ['Title', 'First Name', 'Family Name']) && !empty($value)) {
            if($key == "Country of Citizenship") {
                $countries = [];
                foreach($value as $val2) {
                    array_push($countries, $val2['Country of Citizenship']);
                }
                echo "Country of Citizenship: " . join(', ', $countries) . "<br />";
            }
            else {
                echo "$key: $value<br />";
            }
        }
    }
    ?>

    <h1>Contact Information</h1>

    <h2>Address</h2>
    <table>
        <?php
        foreach(['Address Type', 'Address - Line 1', 'Line 2', 'Line 3', 'City', 'Country'] as $key) {
            echo "<tr>";
            foreach($pi['Address'] as $addr) {
                echo '<td style="padding-bottom: 0px">';
                if($key == 'City') {
                    echo "{$addr['City']} {$addr['Location']['Subdivision']} {$addr['Postal / Zip Code']}";
                }
                elseif($key == 'Country') {
                    echo "{$addr['Location']['Country']}";
                }
                else {
                    echo "{$addr[$key]}";
                }
                echo "</td>";
            }
            echo "</tr>";
        }
        ?>
    </table>

    <h2>Telephone</h2>
    <table>
        <?php
        foreach($pi['Telephone'] as $item) {
            echo "<tr>";
                echo "<td>{$item['Phone Type']}</td>";
                echo "<td>{$item['Country Code']}-{$item['Area Code']}-{$item['Telephone Number']}</td>";
            echo "</tr>";
        }
        ?>
    </table>

    <h2>Email</h2>
    <table>
        <tr>
        <?php
            echo "<td>{$pi['Email']['Email Type']}</td>";
            echo "<td>{$pi['Email']['Email Address']}</td>";
            ?>
        </tr>
    </table>

    <h2>Website</h2>
    <table>
        <tr>
            <?php
            echo "<td>{$pi['Website']['Website Type']}</td>";
            echo "<td>{$pi['Website']['URL']}</td>";
            ?>
        </tr>
    </table>

    <?php $edu = $sections['Education']['Degrees']; ?>
    <h1>Degrees</h1>
    <table>
        <?php
        foreach($edu as $item) {
            ?>
            <tr>
                <td>
                    <?php echo "{$item['Degree Start Date']} - {$item['Degree Received Date']}"; ?>
                </td>
                <td>
                    <?php 
                    echo "{$item['Degree Type']}, {$item['Degree Name']}, {$item['Specialization']}, {$item['Organization']['Organization']} <br />";
                    echo "Degree Status: {$item['Degree Status']}<br /><br />";
                    echo "Supervisors: {$item['Supervisors']['Supervisor Name']}, {$item['Supervisors']['Start Date']} - {$item['Supervisors']['End Date']}"; 
                    ?>
                </td>
            </tr>
        <?php
        }
        ?>
    </table>

    <?php $rec = $sections['Recognitions']; ?>
    <h1>Recognitions</h1>
    <table>
        <?php
        foreach($rec as $item) {
        ?>
            <tr>
                <td style="white-space: nowrap">
                    <?php
                    echo "{$item['Effective Date']}";
                    if(isset($item['End Date']) && !empty($item['End Date'])) {
                        echo "- {$item['End Date']}";
                    }
                    ?>
                </td>
                <td>
                    <?php
                    echo "{$item['Recognition Name']}<br />";
                    if(isset($item['Organization']) && !empty($item['Organization'])) {
                        echo "{$item['Organization']['Organization']}<br />";
                    }
                    elseif(isset($item['Other Organization']) && !empty($item['Other Organization'])) {
                        echo "{$item['Other Organization']}<br />";
                    }
                    echo "{$item['Recognition Type']}";
                    if(isset($item['Description']) && !empty($item['Description'])) {
                        echo "<br />{$item['Description']}";
                    }
                    ?>
                </td>
            </tr>
        <?php
        }
        ?>
    </table>

    <h1>Employment</h1>
    <table>
        <?php
        foreach($emp as $item) {
            if($item['Position Title'] != 'Consultant') {
            ?>
            <tr>
                <td>
                    <?php
                    echo "{$item['Start Date']}";
                    if(isset($item['End Date'])) {
                        echo "- {$item['End Date']}";
                    }
                    ?>
                </td>
                <td>
                    <?php
                    echo "{$item['Position Title']}<br />";
                    if(isset($item['Department']) && !empty($item['Department'])) {
                        echo "{$item['Department']}, ";
                    }
                    if(isset($item['Faculty / School / Campus']) && !empty($item['Faculty / School / Campus'])) {
                        echo "{$item['Faculty / School / Campus']}, ";
                    }
                    if(isset($item['Organization']) && !empty($item['Organization'])) {
                        echo "{$item['Organization']['Organization']}<br />";
                    }
                    else {
                        echo "{$item['Other Organization']}<br />";
                    }
                    if(isset($item['Position Status']) && !empty($item['Position Status'])) {
                        echo "{$item['Position Status']}, ";
                    }
                    if(isset($item['Position Type']) && !empty($item['Position Type'])) {
                        echo "{$item['Position Type']}, ";
                    }
                    if(isset($item['Academic Rank']) && !empty($item['Academic Rank'])) {
                        echo "{$item['Academic Rank']}";
                    }
                    ?>
                </td>
            </tr>
            <?php
            }
        }
        ?>
    </table>

    <?php $funding = $sections['Research Funding History']; ?>
    <h1>Research Funding History</h1>
    <h3>Awarded</h3>
    <table>
        <?php foreach($funding as $item) { ?>
            <tr>
                <td style="white-space: nowrap">
                    <?php echo "{$item['Funding Start Date']} - {$item['Funding End Date']}<br />"; ?>
                    <?php echo "{$item['Funding Role']}"; ?>
                </td>
                <td>
                <?php echo "{$item['Funding Title']}<br /><br />"; ?>
                    <?php if(isset($item['Funding Sources']) && !empty($item['Funding Sources'])) { ?>
                        <b>Funding Sources:</b><br/>
                        <table>
                        <?php $srcs = $item['Funding Sources']; ?>
                        <?php
                            if(!array_key_exists(0, $srcs)) {
                                $srcs = [$srcs];
                            }
                        ?>
                        <?php foreach($srcs as $item2) { ?>
                            <tr>
                                <td>
                                    <?php if(isset($item2['Funding Start Date'])) { ?>
                                        <?php echo "{$item2['Funding Start Date']}"; ?>
                                    <?php } ?>
                                    <?php if(isset($item2['Funding End Date'])) { ?>
                                        <?php echo "- {$item2['Funding End Date']}"; ?>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if(isset($item2['Funding Organization'])) { ?>
                                        <?php echo "{$item2['Funding Organization']}<br />"; ?>
                                    <?php } ?>
                                    <?php if(isset($item2['Program Name'])) { ?>
                                        <?php echo "{$item2['Program Name']}<br />"; ?>
                                    <?php } ?>
                                    <?php if(isset($item2['Total Funding'])) { ?>
                                        <?php echo "Total Funding - $" . number_format($item2['Total Funding']); ?>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </table>
                    <?php } ?>
                </td>
            </tr>
        <?php } ?>
    </table>

    <h1>Courses Taught</h1>
    <table>
        <?php foreach($courses as $key => $value) { ?>
            <tr>
                <td><?php echo $value; ?></td>
                <td><?php echo $key; ?></td>
            </tr>
        <?php } ?>
    </table>

    <h1>Student/Postdoctoral Supervision</h1>
    <?php foreach($students as $key => $value) { ?>
        <h3><?php echo "{$key}"; ?></h2>
        <table>
            <?php foreach($value as $item) { ?>
                <tr>
                    <td>
                        <?php
                        echo "{$item['Supervision Start Date']}";
                        if(isset($item['Supervision End Date']) && !empty($item['Supervision End Date'])) {
                            echo "- {$item['Supervision End Date']}";
                        }
                        ?>
                    </td>
                    <td>
                        <?php echo "{$item['Student Name']}"; ?>
                    </td>
                </tr>
            <?php } ?>
        </table>
    <?php } ?>