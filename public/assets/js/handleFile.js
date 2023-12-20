// Function to handle file selection
var formData = new FormData();

function uploadFiles() {
    
    const xhr = new XMLHttpRequest();

    // Track the upload progress
    xhr.upload.addEventListener('progress', function (event) {
        if (event.lengthComputable) {
            const percentComplete = (event.loaded / event.total) * 100;
            updateProgressBar(percentComplete);
        }
    });

    // Handle the upload completion
    xhr.addEventListener('load', function () {
        console.log('Files uploaded successfully');
    });

    // Handle errors during the upload
    xhr.addEventListener('error', function () {
        console.error('Error uploading files');
    });

    // Open a POST request to the server
    xhr.open('POST', '/api/turnitin/upload', true);

    // Send the form data
    xhr.send(formData);

}


function updateProgressBar(percentComplete) {
    const progressBar = $('.progress-bar');

    // Use the jQuery each() function to iterate over the selected elements
    progressBar.each(function () {
        // Access the current element in the iteration
        var element = $(this);

        // Update the CSS width property
        element.css('width', percentComplete + '%');

        // Update the HTML content
        element.html(Math.round(percentComplete) + '%');
    });
}

function handleFileSelect(event) {

    const fileList = document.getElementById("fileUploadList");


    const files = event.target.files;
    for (let i = 0; i < files.length; i++) {
        const elementToAdd = `<div class="container mt-12" style="color: black; /* flex: 1 1 0%; */ /* display: flex; */ /* flex-direction: column; */ /* align-items: center; */ /* padding: 65px; */ border-width: 2px; border-radius: 8px; border-color: #7e57a3; border-style: solid; /* background-color: rgb(250, 250, 250); */ color: rgb(189, 189, 189); outline: none; /* transition: border 0.24s ease-in-out 0s; */ /* cursor: pointer; */ margin-top: 20px">
        <div class="row align-items-center">
            <div class="col-auto">
                <!-- File Icon -->
                <!-- <img src="assets/img/pdf-file.png" alt="File Icon" class="img-fluid" style="max-width: 50px" /> -->
                <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <rect width="30" height="30" fill="url(#pattern0)"></rect>
                    <defs>
                        <pattern id="pattern0" patternContentUnits="objectBoundingBox" width="1" height="1">
                            <use xlink:href="#image0_4_7" transform="scale(0.0104167)"></use>
                        </pattern>
                        <image id="image0_4_7" width="96" height="96" xlink:href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGAAAABgCAYAAADimHc4AAAACXBIWXMAAAsTAAALEwEAmpwYAAAEf0lEQVR4nO2dO48cRRDHO+ER7HZ5z5AADhAIxOMT8JIwINlCQkJotuvkwBEnebdqLSNOhIREtiwIeH4JsARIPoMgABJDwEMIJAIDEQ8JjozjBvUJzrvr2dmZvZ2t7p76Sx3d1VTv/9fV3TM7fWeMSqVSqVQqlUqlUi1BaIdPOEtvItC3ztJfCJw31Yzqqlx3eBda+qhJw7EigGOGb2gVm/6hwSMO+PdVmo8lABD43dZA8CNfwnwsB5C3BgJa+kDCfJwPIHfA7580J280KS+4UuZjBQDJVwJafisCAHmyleC3mjEAwFQrAS1vRwQgT64SJM3HBQAkByFGAJgShFgBYCoQYgaAKUCIHQDGDiEFABgzhFQAYKwQUgKAMUJIDQDGBiFFABgThFQBYCwQUgaAMUBIHQCGDqENADBkCNIAspsHnaJ+Ocs/twKCOIDu6btn9OulhnKG9aWONAAEeraoX5l58XoPoYlKCAqCPAC+aNqsAADkCHTUtFXy5nOOln9Y7zx3k2mjxM2HfQgfZ/bMmmmbxI2HifZ9H/hR0yYFYHp+bTXQloPRhuucvmfWfUIyEjcbZJu0/wpAWtIjELUC5E3ANk5BfaDHEfiCtAEo3y44GD22UvMd8AsIvBvAh88DabsOaHMl5vc79KCaz4Ug/Dm5pI8jYejN0lbjAJo+94sxN8vbjQMQ/5AQdhMDYFomVACyih4AdulJtPTT9HX8GuOAPnOWyH+tOC9u7/ctf4WWz/d7o/vr5hu7zo+uS8dX7UNtLSux/8A4Zz51lr7I1s7cWiNuB4FfKQJXJR9avrJqH+QqoOKi5ix9Pm5opThLW9MQqsUpgHyGMcPa4IBergXA8hW0fGzVAzGYCjD/6cQa3+Zf+5j8OX06K+6pw5tdhNHTBQfGd7A3uG9ePmkfxBKXxbveqSNTU8qf8+L898HT87yzfK5KvkWkAKAAHFA2Be7LeYYtsgNKGoDrnTrigN6bnM/5k3lxXifW2FatnKI1QMKHYABgSVu3NJgVN37NrLcBCmDZACxfLtuGjl9zHYbPLDIF1d0BlV3PJFUBli/j4eEtZXETizDQd1MAzs6Lk/ZBLHGJ6dt+zvfTTuGjiKnf9/O+H/nXmA+8k3X43llxofgglnjReKw+dZ1fRr66/TjodVMBcHHDbFy3jHx1+3HQ664s8cRNk62+BSx/qEZ/+5E/bf5B8qULoEvH90ypuQPZjxtbM/xuxy+443P+svIlCyB2oQKQVXAAtPFqAOhrKSz7WoqzfElHO8/c/jYPoMsP6KuJXGT+rjtEDzcOYA8C0KZC4H3zHdA/Dvh5s0r5s7nO0jttn46c5bdFDwZKG4DCTcx4BcAKALUC5P98PYrO//SHafs/cEDRRt9I++9vzN5obQUAvyrt//+nJdtaAUdNCGrl4wnLH5pQlAHd7oB+adHI/y2zgztNSPLPQXzH5M3hRpsD/nW9N3rIhCg/KlI+xuosX3KW7zChyx/dR6DX/TYt6vsE6/tOXzvg14JZcFUqlUqlUqlUKpWJXf8CUrUZzk6GiEkAAAAASUVORK5CYII="></image>
                    </defs>
                </svg>
            </div>
            <div class="col text-left" style="margin: 10px 0px 10px 0px">
                <!-- File Name -->
                <span style="font-weight: 700; color: #440b55">${files[i].name}</span><br>
                <!-- File Size -->
                <span class="small" style="color: #440b55; font-weight: 500">${(files[i].size / (1024 * 1024)).toFixed(2)} MB</span>

                <!-- File Progress -->
                <div class="progress mt-2">
                    <div class="progress-bar" role="progressbar" style="width: 100%; background: #7e57a3" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100">50%</div>
                </div>
            </div>
        </div>
    </div>`;

        formData.append('files[]', files[i]);
        fileList.insertAdjacentHTML('beforeend', elementToAdd);
    }
}

// Attach the function to the file input change event
const fileInput = document.getElementById("upload_dokumen");
fileInput.addEventListener("change", handleFileSelect);

$(document).ready(() => {
    formData.append('phone_number', '082114080612');
    $("#submit-file").click(uploadFiles);
})
