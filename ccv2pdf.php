<html>
    <head>
        <link rel="stylesheet" href="node_modules/filepond/dist/filepond.css">
        <link rel="stylesheet" href="style.css">
        <title>Convert CCV Export to minimized version</title>
        <style>
            
        </style>

    </head>
    <body>
        <div class="m-auto p-8 w-full h-1/6 columns-1">
            <!-- <form action="convert.php" method="post" enctype="multipart/form-data"> -->
                <div>
                    <input type="file" class="filepond" name="ccv">
                </div>
                <!-- <input type="submit" value="Upload" name="submit" class="p-2 bg-blue-400"> -->
            <!-- </form> -->
        </div>

        <div class="m-auto p-2 w-full h-5/6" id="preview">
            <div class="hidden text-center" id="processing">
                <span>Processing XML...</span>
                <svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><style>.spinner_P7sC{transform-origin:center;animation:spinner_svv2 .75s infinite linear}@keyframes spinner_svv2{100%{transform:rotate(360deg)}}</style><path d="M10.14,1.16a11,11,0,0,0-9,8.92A1.59,1.59,0,0,0,2.46,12,1.52,1.52,0,0,0,4.11,10.7a8,8,0,0,1,6.66-6.61A1.42,1.42,0,0,0,12,2.69h0A1.57,1.57,0,0,0,10.14,1.16Z" class="spinner_P7sC"/></svg>
            </div>
            <a class="p-2 my-2 text-blue-700 hidden" id="download">Download</a>
            <div class="w-full h-5/6 hidden" id="embed">
                <embed width="100%" height="100%" type="application/pdf">
            </div>
        </div>
        
        <script src="node_modules/filepond/dist/filepond.js"></script>
        <script src="node_modules/filepond-plugin-file-validate-type/dist/filepond-plugin-file-validate-type.js"></script>
        <script>
            FilePond.registerPlugin(FilePondPluginFileValidateType)
            var pond = FilePond.create(document.querySelector('input'), {
                server: {
                    process: {
                        url: 'convert.php',
                        onload: (resp, arg2) => {
                            document.querySelector('#processing').classList.add('hidden');
                            var dl = document.querySelector('#download');
                            dl.href = resp;
                            dl.classList.remove('hidden');
                            document.querySelector("#embed embed").src = resp;
                            document.querySelector("#embed").classList.remove('hidden');
                        }
                    }
                },
                instantUpload: false,
                acceptedFileTypes: ['application/xml', 'text/xml'],
                labelIdle: 'Drag & Drop CCV XML export or <span class="filepond--label-action">Browse</span>'
            });
            pond.onprocessfilestart = (file) => {
                document.querySelector('#processing').classList.remove("hidden");
            };
        </script>
    </body>
</html>