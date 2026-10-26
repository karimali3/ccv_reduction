<?php
    ini_set('upload_max_filesize', '10M');
    $input = $_FILES['ccv'];
    $res = 0;
    $out1 = '';
    #$output = exec(dirname(__FILE__) . "/ccv_conda/bin/python xml2html_cv.py --input " . $input['tmp_name'] . " 2>&1", $out1, $res);
    $command = dirname(__FILE__) . "/ccv_conda/bin/python xml2html_cv.py --input " . escapeshellarg($input['tmp_name']) . " 2>/dev/null";
    $pdf_data = shell_exec($command);
    if($pdf_data === null || empty($pdf_data)) {
            http_response_code(500);
            echo "Error: failed to generate pdf";
            die();
    }
    //$output = exec("ccv/bin/python xml2html_cv.py --input RG-Generic-CCV-2023.12.12.xml", $out1, $res);
    #$base = pathinfo($input['tmp_name'], PATHINFO_FILENAME);
    #$out = pathinfo($input['name'], PATHINFO_FILENAME);
    #rename("$base.pdf", "$out.pdf");
    #if(file_exists("$out.pdf")) {
    #    print("$out.pdf");
    #}
    $pdf_base64 = base64_encode($pdf_data);
    $data_uri = 'data:application/pdf;base64,' . $pdf_base64;

    echo $data_uri;
?>