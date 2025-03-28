<?php
// this is a concrete5 way to make the main part editable
$a = new Area('Main');
$a->display($c);
?>

<?php
require('coh_server_lib.php');//contains function check_user
check_user(0, $check_academic=1 ); // first variable 0/1 is debug, it echo to screen the User ID and whether it's academic
?>

    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>File Upload</title>
        <!-- Bootstrap CSS -->
        <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
        <style>
            .header-wrapper {
                display: flex;
                align-items: center;
            }

            .header-image {
                width: 50px; /* Adjust as necessary */
                height: auto;
                margin: 0 10px; /* Adjust margins as necessary */
            }

            .left {
                order: -1; /* Place image on the left */
            }

            .right {
                order: 1; /* Place image on the right */
            }

            .custom-file-label #fileName {
                display: inline-block;
                max-width: calc(100% - 32px); /* Adjust width to fit input box */
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .plot-container {
                background-color: white; /* Background color */
                margin: 0;
                padding: 1%;
                height: 100%;
                width: 100%;
                border-radius: 2%;
                /* max-height: 60%; */
            }

            .custom-img-fluid {
                max-width: 100%;
                max-height: 100%;
                height: auto;
            }

            .img-wrapper {
                max-width: 80%;
                max-height: 80%;
                height: 80%;
                width: 80%;
            }

            html, body {
                background-color: #F0F7FF;
                height: 100%;
                margin: 0;
            }

            /* Disabled button style */
            #processButton:disabled {
                background-color: #6c757d; /* Grey background */
                cursor: not-allowed; /* Disabled cursor */
            }

            /* Hover effect for disabled button */
            #processButton:disabled:hover {
                background-color: #6c757d; /* Keep grey background on hover */
            }

            .small-padding {
                padding: 0 0.5%;
            }

            .bg-light-transparent {
                background-color: rgba(240, 247, 255, 0.3); /* Adjust transparency */
            }

            .disabled-label {
                color: #868e96; /* Grey color */
            }

            #report .card {
                flex-grow: 1; /* Equal space for cards */
            }

            #report .d-flex {
                flex-direction: column; /* Stack cards vertically */
            }

            #manualKSelection input[type="number"].form-control.gray-text {
                color: gray !important;
            }

            /* Ensure equal height for columns */
            #report .col-md-6 {
                display: flex;
            }

            #report .col-md-6 .d-flex.flex-column {
                flex: 1;
            }

            /* Adjust image size inside shorter column */
            #report .card img {
                max-width: 100%; /* Fit image within container */
                height: auto;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="row">
                <div class="col">
                    <div class="header-wrapper mb-4 d-flex justify-content-center align-items-center text-center">
                        <img src="/concrete/uploaded_files/web_site_images/dropfit_before.svg" 
                            alt="Before Image" class="header-image left">
                        <div class="d-flex flex-column align-items-center">
                            <h1 class="mb-2">DropFit</h1>
                            <h5 class="mb-2">Critical Concentration Calculator</h5>
                        </div>
                        <img src="/concrete/uploaded_files/web_site_images/dropfit_after.svg" 
                            alt="After Image" class="header-image right">
                    </div>
                    <p class="small text-muted align-items-center text-center justify-content-center">A tool for calculating concentration when phase separation occurs (critical concentration) from droplet data collected at concentrations below the critical concentration. See the original <a href="https://elifesciences.org/articles/94214" target="_blank"> paper</a> for more details.</p>
                    <form id="concentrationCalculationForm" enctype="multipart/form-data">
                        <div class="d-block">
                            <h5>1. Choose a file *</h5>
                        </div>
                        <div class="small text-muted mb-4 text-justify">
                            <p>
                                Choose a file containing concentrations and corresponding droplet sizes. The data should:
                            </p>
                            <ul>
                                <li>contain at least 3 different concentrations (The server will work also with only 2 concentrations, however, the results will most likely be very inaccurate.)</li>
                                <li><strong>not</strong> contain concentrations above the critical concentration as the model would not apply in such cases.</li>
                                <li>contain droplet sizes in rows with the corresponding concentration in the header (see the <a href="/concrete/uploaded_files/example_files/example.csv" download>example file</a> for reference).</li>
                                <li>contain, if possible, replicate columns for concentrations (each should have the corresponding concentration in its header).</li>
                                <li>be in <strong>csv</strong> format.</li>
                            </ul>
                        </div>                   
                        <div class="input-group mb-3">
                            <div class="custom-file">
                                <input type="file" id="fileInput" name='userfile'>
                                <label class="custom-file-label text-truncate" for="fileInput">Choose file</label>
                            </div>
                        </div>
                        <h5> 2. Pick <b><em>k</em></b> value *</h5>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="" id="autoKSelect" name="autoKSelect" checked>
                            <label class="form-check-label" for="autoKSelect">Automatic</label>
                        </div>
                        <div id="manualKSelection">
                            <label id='manualKLabel'>Select at least 1 and up to 4 <b><em>k</em></b> values:</label>
                            <div class="input-group mb-3">
                                <input type="number" class="form-control mr-2" id="k1" name="k1" min="0.001" max="9999" step="0.001">
                                <input type="number" class="form-control mr-2" id="k2" name="k2" min="0.001" max="9999" step="0.001">
                                <input type="number" class="form-control mr-2" id="k3" name="k3" min="0.001" max="9999" step="0.001">
                                <input type="number" class="form-control mr-2" id="k4" name="k4" min="0.001" max="9999" step="0.001">
                                <div class="input-group-append">
                                    <button class="btn btn-danger" type="button" id="eraseKButton">Erase</button>
                                </div>
                            </div>
                            <div id="atLeast1InputManualKMessage" style="color: #DC3545; display: none;">At least 1 value must be selected. </div>
                            <div id="validationManualKMessage" style="color: #DC3545; display: none;">Invalid input. Please enter a valid value.</div>
                        </div>
                        <p class="small text-muted mb-4 text-justify">* required</p>
                        <div class="input-group-append justify-content-center mt-4 mb-4"> <!-- Center the button and add margin -->
                            <button class="btn btn-primary" type="submit" id="processButton" disabled>Process</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <div id="loadingBar" class="progress mb-3" style="display: none;">
                        <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col justify-content-md-center">
                    <div id="errorMessage" class="alert alert-danger d-none text-center" role="alert">
                    </div>
                </div>
            </div>
            <div class="row justify-content-md-center" id="report">
                <div class="col mb-4 d-flex align-items-stretch">
                    <div class="d-flex flex-column w-100">
                        <div id="concentrationValueContainer" class="card shadow-sm text-center p-3 mb-2" style="display: none !important;">
                            <!-- Content will be added here after user submits the data. -->
                        </div>
                        <div id="concentrationPlotContainer" class="card shadow-sm text-center p-3" style="display: none !important;">
                            <!-- Content will be added here after user submits the data. -->
                        </div>
                    </div>
                </div>
                <div class="col mb-4 d-flex align-items-stretch">
                    <div class="d-flex flex-column w-100">
                        <div id="concentrationChoiceContainer" class="card shadow-sm text-center p-3 mb-2" style="display: none !important;">
                            <!-- Content will be added here after user submits the data. -->
                        </div>
                        <div id="collapsePlotContainer" class="card shadow-sm text-center p-3" style="display: none !important;">
                            <!-- Content will be added here after user submits the data. -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer Section -->
        <footer class="fixed-bottom bg-light-transparent py-1">
            <div class="container text-center">
                <p class="small text-muted mb-0">The calculator is based on the paper <a href="https://elifesciences.org/articles/94214" target="_blank">A scale-invariant log-normal droplet size distribution below the transition concentration for protein phase separation</a>.</p>
            </div>
        </footer>
        <!-- End of Footer Section -->
        
        <!-- JavaScript -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
                $(document).ready(function () {

            // Cache commonly used elements
            var concentrationCalculationForm = $('#concentrationCalculationForm');
            var concentrationPlotContainer = $('#concentrationPlotContainer');
            var concentrationValueContainer = $('#concentrationValueContainer');
            var collapsePlotContainer = $('#collapsePlotContainer');
            var concentrationChoiceContainer = $('#concentrationChoiceContainer');
            var errorMessage = $('#errorMessage');
            var loadingBar = $('#loadingBar');
            var progressBar = $('#progressBar');
            var fileInput = $('#fileInput');
            var processButton = $('#processButton');
            var autoKSelectCheckbox = $('#autoKSelect');
            var manualKSelection = $('#manualKSelection');
            var manualKLabel = $('#manualKLabel');
            var eraseKButton = $('#eraseKButton');
            var atLeast1InputManualKMessage = $('#atLeast1InputManualKMessage')[0];
            var validationManualKMessage = $('#validationManualKMessage')[0];

            var checkboxStates = {};
            var spinnerHtml = '<div><div class="spinner spinner-border text-primary" style="display: none !important;" role="status">' +
                        '<span class="sr-only">Loading...</span>' +
                        '</div></div>';
            // Initially disable the process button
            processButton.prop('disabled', true);

            // Event listener for form submission. Run critical concentration calculation.
            concentrationCalculationForm.submit(function (event) {
                event.preventDefault();

                // Empty containers
                emptyResultsContainers();

                // Create formData object
                var formData = new FormData(this);

                // AJAX request to call function to calculate the critical concentration
                $.ajax({
                    url: "/index.php/dropfit/getcriticalconcentration",
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    // dataType: 'json',
                    xhr: function () {
                        var xhr = new window.XMLHttpRequest();
                        // Upload progress
                        xhr.upload.addEventListener("progress", function (evt) {
                            if (evt.lengthComputable) {
                                var percentComplete = (evt.loaded / evt.total) * 100;
                                loadingBar.show();
                                progressBar.css('width', percentComplete + '%').attr('aria-valuenow', percentComplete);
                            }
                        }, false);
                        return xhr;
                    },
                    success: function (response) {
                        console.log(response); //TODO: delete

                        // If the calculations were successful and outputs produced, show them
                        if (response.concentration_plot && response.concentration && response.collapse_plot) {

                            // Process the plot data from the form they were sent in to a form that can be added
                            // to the page
                            const concentrationPlotSrc = "data:image/png;base64," + response.concentration_plot;
                            const collapsePlotSrc = "data:image/png;base64," + response.collapse_plot;

                            // Show plot container
                            concentrationPlotContainer.append('<div class="card-body"><h6 class="card-title">Determination of Critical Concentration</h6></div>');
                            concentrationPlotContainer.append('<img id="concentrationPlot" src="' + concentrationPlotSrc + '" class="card-img-bottom">');
                            concentrationPlotContainer.append('<div><a id="concentrationPlotDownloadButton" href="' + concentrationPlotSrc + '" download="critical_concentration_plot.png" class="btn btn-primary mt-2 mr-1">Download PNG</a></div>');
                            concentrationPlotContainer.append(spinnerHtml); // Add spinner for possible regeneration of plots 
                            concentrationPlotContainer.show();

                            // Show value container
                            concentrationValueContainer.append('<div class="card-body"><h6 class="card-title">Critical Concentration</h6></div>');
                            concentrationValueContainer.append('<h3 id="concentrationValue" class="font-weight-bold" style="color:#007bff;">' + response.concentration + '</h3>');
                            concentrationValueContainer.append(spinnerHtml); // Add spinner for possible regeneration of plots 
                            concentrationValueContainer.show();

                            // Show concentrations' choice container
                            concentrationChoiceContainer.append('<div class="card-body"><h6 class="card-title"> Choose concentrations to <b>leave out </b> (Optional) </h6></div>');
                            concentrationChoiceContainer.append('<div><p id="carefulWithConcentrationsLabel" class="small text-muted text-center mb-4">Make sure your concentrations do not exceed the assumed critical concentration. </p></div>');
                            concentrationChoiceContainer.append('<div><p id="moreThan10ConcentrationsLabel" class="small text-muted text-center mb-4" style="display:none">There are more than 10 concentrations,selection of the first five and last five can be left out. </p></div>');
                            concentrationChoiceContainer.append('<div id="concentrationsToLeaveOut" class="row justify-content-center">');
                            createConcentrationCheckboxes($("#concentrationsToLeaveOut"), response.concentration_usage);
                            concentrationChoiceContainer.append('<div class="input-group-append justify-content-center mt-2 mb-2"><button type="button" class="btn btn-primary" id="regenerateButton"> Regenerate</button></div>');
                            concentrationChoiceContainer.show();
                            saveCheckboxState();

                            // Show colapse plot container
                            collapsePlotContainer.append('<div class="card-body"><h6 class="card-title">Collapse of distributions at various concentrations</h6></div>');
                            collapsePlotContainer.append('<img id="collapsePlot" src="' + collapsePlotSrc + '" class="card-img-bottom"></div>');
                            collapsePlotContainer.append('<div><a id="collapsePlotDownloadButton" href="' + collapsePlotSrc  + '" download="collapse_plot.png" class="btn btn-primary mt-2 mr-1">Download PNG</a></div>');
                            collapsePlotContainer.append(spinnerHtml); // Add spinner for possible regeneration of plots 
                            collapsePlotContainer.show();

                            // Hide the loading bar after the data is generated and shown
                            loadingBar.hide();
                            progressBar.css('width', 0 + '%').attr('aria-valuenow', 0);
                        
                        // Else if there was an error, do not show content but rather error message sent from backend.
                        } else if (response.error) {
                            loadingBar.hide();
                            progressBar.css('width', 0 + '%').attr('aria-valuenow', 0);
                            errorMessage.html(response.error);
                            errorMessage.removeClass('d-none');

                        // In any other case, show generic unknown error message.
                        } else {
                            loadingBar.hide();
                            progressBar.css('width', 0 + '%').attr('aria-valuenow', 0);
                            errorMessage.html("Unknown error. Check your file and try again later.");
                            errorMessage.removeClass('d-none');
                        }
                    },
                    // error: function(errMsg) {
                    //     alert(errMsg);
                    // }
                    error: function () {
                        // console.log(formData);
                        loadingBar.hide();
                        progressBar.css('width', 0 + '%').attr('aria-valuenow', 0);
                        errorMessage.html("Unknown error. Check your file and try again later.");
                        errorMessage.removeClass('d-none');
                    }
                });
            });

            // Define what should happen if user clicks regenerate button (with updated concentrations selected). 
            // Run new critical concentration calculation.
            $(document).on('click', '#regenerateButton', function() {
                console.log("Regeneration Called");
                // Get checked values from checkboxes
                // Initialize an array to store checked values
                var concentrationsToOmit = [];

                // Iterate over each checkbox
                $('#concentrationsToLeaveOut input[type="checkbox"]').each(function() {
                    // If the checkbox is checked, add its value to the array
                    if ($(this).is(':checked')) {
                        concentrationsToOmit.push($(this).val());
                    }
                });

                // Convert the array to a comma-separated string
                var concentrationsToOmitString = concentrationsToOmit.join(',');

                // Create formData object
                var formData = new FormData(concentrationCalculationForm[0]);

                // Append checked values to formData
                formData.append('concentrationsToOmit', concentrationsToOmitString);
            
                hideContainerBodyContent();
                errorMessage.empty().addClass('d-none');
                
                // AJAX request to call function to calculate the critical concentration
                $.ajax({
                    url: '/index.php/dropfit/getcriticalconcentration',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function (response) {
                        // If the calculations were successful and outputs produced, show them
                        if (response.concentration_plot && response.concentration && response.collapse_plot) {

                            // Process the plot data from the form they were sent in to a form that can be added
                            // to the page
                            const concentrationPlotSrc = "data:image/png;base64," + response.concentration_plot;
                            const collapsePlotSrc = "data:image/png;base64," + response.collapse_plot;

                            // Update plots and concentration value
                            $('#concentrationValue').text(response.concentration);
                            $('#concentrationPlot').attr('src', concentrationPlotSrc);
                            $('#concentrationPlotDownloadButton').attr('href', concentrationPlotSrc);
                            $('#collapsePlot').attr('src', collapsePlotSrc);
                            $('#collapsePlotDownloadButton').attr('href', collapsePlotSrc);
                            createConcentrationCheckboxes($("#concentrationsToLeaveOut"), response.concentration_usage);

                            showContainerBodyContent();
                            saveCheckboxState();

                        // Else if there was an error, do not update the content but rather error message sent from backend.
                        } else if (response.error) {
                            errorMessage.html("Regeneration did not work. " + response.error);
                            errorMessage.removeClass('d-none');
                            showContainerBodyContent();
                            retrieveCheckboxState();

                        // In any other case, do not update the content and show generic unknown error message.
                        } else {
                            errorMessage.html("Regeneration did not work. " + "Unknown error. Check your file and try again later.");
                            errorMessage.removeClass('d-none');
                            showContainerBodyContent();
                            retrieveCheckboxState();
                        }
                    },
                    error: function () {
                        errorMessage.html("Regeneration did not work. " + "Unknown error. Check your file and try again later.");
                        errorMessage.removeClass('d-none');
                        showContainerBodyContent();
                        retrieveCheckboxState();
                    }
                });

            })

            // Event listener for autoKSelect checkbox change
            autoKSelectCheckbox.change(function () {
                var autoKSelect = this.checked;
                toggleManualKSelection(autoKSelect);
                checkInputs();
            });

            // Event listener for erase button
            eraseKButton.click(function () {
                // Clear all input fields
                manualKSelection.find('input[type="number"]').val('');
            });

            // Function to toggle manual selection section
            function toggleManualKSelection(disabled) {
                if (disabled) {
                    manualKSelection.addClass('disabled-section');
                    manualKLabel.addClass('disabled-label');
                    manualKSelection.find('input, button').prop('disabled', true);
                    validationManualKMessage.style.display = "none"; // Hide the element
                } else {
                    manualKSelection.removeClass('disabled-section');
                    manualKLabel.removeClass('disabled-label');
                    manualKSelection.find('input, button').prop('disabled', false);
                    manualKSelection.find('input[type="number"]').removeClass('grey-text');
                }
            }

            // Function to empty results containers
            function emptyResultsContainers(){
                // Empty containers
                concentrationPlotContainer.empty().hide();
                concentrationValueContainer.empty().hide();
                collapsePlotContainer.empty().hide();
                concentrationChoiceContainer.empty().hide();
                errorMessage.empty().addClass('d-none');
            }

            // Function to show container body content. Hide spinners and display the plots and value
            function showContainerBodyContent(){
                $('.spinner').hide();
                $('#concentrationPlot').show();
                $('#concentrationPlotDownloadButton').show();
                $('#concentrationValue').show();
                $('#collapsePlot').show();
                $('#collapsePlotDownloadButton').show();
            }

            // Function to hide container body content. Show spinners and hide the plots and value
            function hideContainerBodyContent(){
                $('.spinner').show();
                $('#concentrationPlot').hide();
                $('#concentrationPlotDownloadButton').hide();
                $('#concentrationValue').hide();
                $('#collapsePlot').hide();
                $('#collapsePlotDownloadButton').hide();
            }

            // Function to check inputs. This shows warning messages and disables process button if needed.
            function checkInputs() {
                var fileSelected = fileInput.val();
                var autoKSelected = autoKSelectCheckbox.prop('checked');
                var inputsPopulated = false;
            
                // Check if any input of type number is populated
                manualKSelection.find('input[type="number"]').each(function() {
                    if ($(this).val().trim() !== '') {
                        inputsPopulated = true;
                        return false; // Exit the loop early if any input is populated
                    }
                });
            
                // Display message requiring at least 1 manual k value input if automatic is not selected.
                if(!inputsPopulated && !autoKSelected){
                    atLeast1InputManualKMessage.style.display = "block";
                } else {
                    atLeast1InputManualKMessage.style.display = "none";
                }

                // Enable processButton if any input is populated or autoKSelectCheckbox is checked and file is selected
                if ((inputsPopulated || autoKSelected) && fileSelected) {
                    processButton.prop('disabled', false);
                } else {
                    processButton.prop('disabled', true);
                }
            }
                
            // Check validity
            function checkValidity(id) {
                var input = document.querySelector('#' + id);
                if (!input.validity.valid) {
                    validationManualKMessage.style.display = "block"; // Show the element
                    input.value = ""; // Clear the input value
                } else {
                    validationManualKMessage.style.display = "none"; // Hide the element
                }
            }

            // Show concentrations checkboxes for the concentrations container based on the 
            // concentrations usage sent from the backend.
            function createConcentrationCheckboxes(checkboxRow, concentrationUsage) {
                checkboxRow.empty();
                var numToShow = 10; // Number of checkboxes to show
                var concentrationArray = Object.keys(concentrationUsage);
                
                // If concentrationUsage has more than 10 elements, take the first 5 and last 5
                if (concentrationArray.length > numToShow) {
                    $("#moreThan10ConcentrationsLabel").show();
                    concentrationArray = concentrationArray.slice(0, 5).concat(concentrationArray.slice(-5));
                } else {
                    $("#moreThan10ConcentrationsLabel").hide();
                }
            
                concentrationArray.forEach(function(concentration) {
                    // Create a div with form-check classes
                    var formCheckDiv = $('<div class="form-check mr-3">');
                    // Create checkbox element
                    var checkbox = $('<input class="mr-3" type="checkbox">');
                    
                    // Set checkbox attributes
                    checkbox.attr('id', concentration); // Set id attribute
                    checkbox.attr('name', concentration); // Set name attribute
                    checkbox.val(concentration); // Set value attribute
                    checkbox.prop('checked', !concentrationUsage[concentration]); // Check the checkbox by default
                    
                    // Create label element for the checkbox
                    var label = $('<label class="form-check-label">');
                    label.attr('for', concentration); // Set for attribute to match checkbox id
                    label.text(concentration); // Set label text
                    label.attr('title', concentration);
            
                    // Append checkbox and label to form-check div
                    formCheckDiv.append(checkbox);
                    formCheckDiv.append(label);
            
                    // Append form-check div to row
                    checkboxRow.append(formCheckDiv);
                });
            }
            

            // Function to save checkbox state to the object
            function saveCheckboxState() {
                $('#concentrationsToLeaveOut input[type="checkbox"]').each(function() {
                    var checkboxValue = $(this).val();
                    var isChecked = $(this).is(':checked');
                    checkboxStates[checkboxValue] = isChecked;
                });
            }

            // Function to retrieve checkbox state from the object
            function retrieveCheckboxState() {
                $('#concentrationsToLeaveOut input[type="checkbox"]').each(function() {
                    var checkboxValue = $(this).val();
                    var isChecked = checkboxStates[checkboxValue];
                    if (isChecked !== undefined) {
                        $(this).prop('checked', isChecked);
                    }
                });
            }

            // Event listener for file input change. If there is a change, then empty
            // the results container
            fileInput.change(function () {
                var fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').html(fileName);
                // Check all inputs
                checkInputs();
                // Empty containers
                emptyResultsContainers();

            });

            // For all number inputs change - check their validity and also check overall inputs
            // and update processButton and warning messages accordiningly.
            $('input[type="number"]').change(function () {
                checkValidity(this.id);
                checkInputs();
            });

            // Initially disable manual selection if autoKSelect is checked
            toggleManualKSelection(autoKSelectCheckbox.prop('checked'));
        });
        </script>
    </body>
    </html>

<?php
// this is a concrete5 way to make the main part editable
$a = new Area('Footer');
$a->display($c);
