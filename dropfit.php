<?php
// this is a concrete5 way to make the main part editable
$a = new Area('Main');
$a->display($c);
?>

<?php
require('coh_server_lib.php');//contains function check_user
check_user(0, $check_academic=0 ); // first variable 0/1 is debug, it echo to screen the User ID and whether it's academic
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
            #processButton:disabled,
            #getDataFitnessReportButton:disabled {
                background-color: #6c757d; /* Grey background */
                cursor: not-allowed; /* Disabled cursor */
            }

            /* Hover effect for disabled button */
            #processButton:disabled:hover,
            #getDataFitnessReportButton:disabled:hover {
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

            #results .card {
                flex-grow: 1; /* Equal space for cards */
            }

            #results .d-flex {
                flex-direction: column; /* Stack cards vertically */
            }

            #manualKSelection input[type="number"].form-control.gray-text {
                color: gray !important;
            }

            /* Ensure equal height for columns */
            #results .col-md-6 {
                display: flex;
            }

            #results .col-md-6 .d-flex.flex-column {
                flex: 1;
            }

            /* Adjust image size inside shorter column */
            #results .card img {
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
                    <p class="small text-muted align-items-center text-center justify-content-center">A tool for calculating concentration when phase separation occurs (critical concentration) from droplet data collected at concentrations below the critical concentration. See the original <a href="https://elifesciences.org/articles/94214" target="_blank"> paper</a>, the DropFit <a href="https://www.sciencedirect.com/science/article/pii/S0022283625003602?via%3Dihub" target="_blank"> paper</a> or our <a href="https://docs.google.com/document/d/1uEcZJkTb3oZFkOtOvRY8i_0Jb93ceCwysiYo41CGtGQ/edit?usp=sharing" target="_blank"> user manual </a> for more details. The manual also contains walk-through of the main calculations behind the results as well as updates on changes made to the server. If your results do not look right and the data seems correct, please, contact us on <b>mb2462 [at] cam.ac.uk </b> </p>
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
                                <li>contain droplet sizes in rows with the corresponding concentration in the header (see the <a href="/available_downloads/example_dropfilt_input.csv" download>example file</a> for reference). Each row should correspond to one data point.</li>
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
            <div class="row justify-content-md-center" id="results">
                <div class="col-12 col-md-6 mb-4 d-flex align-items-stretch">
                    <div class="d-flex flex-column w-100">
                        <div id="concentrationValueContainer" class="card shadow-sm text-center p-3 mb-2" style="display: none !important;">
                            <div class="card-body"><h6 class="card-title">Critical Concentration</h6></div>
                            <h3 id="concentrationValue" class="font-weight-bold" style="color:#007bff;"></h3>
                            <div><div class="spinner spinner-border text-primary" style="display: none !important;" role="status">
                                <span class="sr-only">Loading...</span>
                            </div></div>
                        </div>
                        <div id="concentrationPlotContainer" class="card shadow-sm text-center p-3" style="display: none !important;">
                            <div class="card-body"><h6 class="card-title">Determination of Critical Concentration</h6></div>
                            <div class="d-flex flex-column flex-md-row justify-content-center align-items-center">
                                <img id="concentrationPlot" class="card-img-bottom" class="img-fluid" style="max-width: 400px; width: 100%; height: auto;">
                            </div>
                            <div><a id="concentrationPlotDownloadButton" download="critical_concentration_plot.png" class="btn btn-primary text-white btn btn-primary mt-2 mr-1">Download PNG</a></div>
                            <div><div class="spinner spinner-border text-primary" style="display: none !important;" role="status">
                                <span class="sr-only">Loading...</span>
                            </div></div>
                        </div>
                        <!-- Uncomment when data report is active -->
                        <div id="showDataReportButtonContainer" class="text-center p-3 flex-grow-1" style="display: none !important; background: transparent; border: none; box-shadow: none;">
                            <button type="button" class="btn btn-success text-white" id="showDataReportButton">
                                Model Validation
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col mb-4 d-flex align-items-stretch">
                    <div class="d-flex flex-column w-100">
                        <div id="concentrationChoiceContainer" class="card shadow-sm text-center p-3 mb-2" style="display: none !important;">
                            <div class="card-body"><h6 class="card-title"> Choose concentrations to <b>leave out </b> (Optional) </h6></div>
                            <div><p id="moreThan10ConcentrationsLabel" class="small text-muted text-center mb-4" style="display:none">There are more than 10 concentrations,selection of the first five and last five can be left out. </p></div>
                            <div id="concentrationsToLeaveOut" class="row justify-content-center"></div>
                            <div class="input-group-append justify-content-center mt-2 mb-2"><button type="button" class="btn btn-primary" id="regenerateButton"> Regenerate</button></div>
                        </div>
                        <div id="collapsePlotContainer" class="card shadow-sm text-center p-3" style="display: none !important;">
                            <div class="card-body"><h6 class="card-title">Collapse of distributions at various concentrations</h6></div>
                            <div class="d-flex flex-column flex-md-row justify-content-center align-items-center">
                                <img id="collapsePlot" class="card-img-bottom" class="img-fluid" style="max-width: 400px; width: 100%; height: auto;">
                            </div>
                            <div><a id="collapsePlotDownloadButton" download="collapse_plot.png" class="btn btn-primary mt-2 mr-1">Download PNG</a></div>
                            <div><div class="spinner spinner-border text-primary" style="display: none !important;" role="status">
                                <span class="sr-only">Loading...</span>
                            </div></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row justify-content-md-center align-items-stretch" id="dataFitnessReportWrapper">
                <div class='col'>
                    <div id="dataFitnessReportContainer" class="card shadow-sm p-3 mb-2" style="display: none !important;">
                        <!-- Title -->
                        <div class="text-center mb-4" id="dataReportTitle">
                            <h5>Model Validation</h5>
                        </div>
                        <div class="d-flex justify-content-center">
                            <div class="mb-3">
                                <h6 class="mb-2">Choose critical concentration to use to see if your data fits the model*</h6>
                                <div class="text-muted mb-2" style="text-align: left;">The critical concentration to use should be larger than the concentrations in the data.</div>
                                <div class="d-flex align-items-center" style="gap: 2rem;">
                                    <!-- Automatic Radio Option -->
                                    <div class="form-check mb-0 d-flex align-items-center" style="gap: 0.5rem;">
                                        <input class="form-check-input" type="radio" name="criticalConcentrationOption" id="autoCriticalConcentration" value="auto" checked>
                                        <label class="form-check-label" for="autoCriticalConcentration">
                                        Automatic (DropFit-calculated)
                                        </label>
                                    </div>

                                    <!-- Manual Radio Option with Input -->
                                    <div class="form-check mb-0 d-flex align-items-center" style="gap: 0.5rem;">
                                        <input class="form-check-input" type="radio" name="criticalConcentrationOption" id="manualCriticalConcentration" value="manual">
                                        <label class="form-check-label mb-0" for="manualCriticalConcentration">Other:</label>
                                        <input type="number" step="any" class="form-control form-control-sm w-auto" id="dataFitnessReportCriticalConcentrationInput" min="0.001" max="9999" disabled>
                                    </div>

                                    <!-- Submit Button -->
                                    <button type="button" class="btn btn-sm btn-primary" id="getDataFitnessReportButton">Analyse</button>
                                </div>
                                <div id="dataFitnessReportValidationConcentrationEmptyMessage" style="color: #DC3545; display: none;">Value of critical concentration is required. </div>
                                <div id="dataFitnessReportValidationConcentrationInvalidValueMessage" style="color: #DC3545; display: none;">Please enter a valid value. Remember, the value should be larger than the concentrations in the used data.</div>
                                <p class="small text-muted mb-4 text-justify">* required</p>

                                <div class="d-flex justify-content-center align-items-center mt-3">
                                    <div class="spinner spinner-border text-primary" id="dataFitnessReportSpinner" style="display: none !important;" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id='dataReportResults' style="display: none !important;">
                            <hr class="my-4 border-secondary">
                            <!-- <div class="text-center mb-4" id="dataReportTitle">
                                <h6>Report</h6>
                            </div> -->
                            <!-- Sanity Check Section -->
                            <!-- <div class="mb-2">
                                <h6 id="sanityIssuesTitle" style="text-align: left;">1. Potential sanity issues identified within the data (independent of the critical concentration)</h6>
                            </div>
                            
                            <ul id="sanityCheckWarnings" class="text-muted mx-auto text-start px-3" 
                                style="
                                    column-count: 2;
                                    column-gap: 2rem;
                                    list-style-position: inside;
                                    padding-bottom: 1rem;
                                    margin: 0 auto;
                                    text-align: left;
                                    display: none !important;
                                ">
                            </ul> -->

                            <div id="warningMessageModelValidation" class="alert alert-warning text-center" style="display: none !important;" role="alert">
                                <strong>Warnings</strong>
                                <ul id="sanityCheckWarnings" class="mx-auto text-start px-3"
                                    style="
                                        column-count: 2;
                                        column-gap: 2rem;
                                        list-style-position: inside;
                                        padding-bottom: 1rem;
                                        margin: 0 auto;
                                        text-align: left;
                                        display: none !important;
                                    ">
                                </ul>
                            </div>

                            <!-- <div id="noIssuesFoundText" class="text-muted px-3" style="text-align: left;">No issues found.</div> -->

                            <!-- <hr class="my-4 border-secondary-subtle"> -->

                            <!-- Collapse Verification Section -->
                            <div class="mb-2">
                                <h6 id="collapseVerificationTitle" style="text-align: left;">1.  Collapse Verification</h6>
                            </div>

                            <!-- Plots -->
                            <div id='collapseVerificationPlots' class="d-flex flex-column flex-md-row justify-content-center align-items-center gap-3 my-3">
                                <img id="kdesPlot" src="" alt="KDE Plot"
                                    class="img-fluid" style="max-width: 300px; width: 100%; height: auto;">
                                <img id="concentrationsCollapsePlot" src="" alt="Critical Concentration Collapse Plot"
                                    class="img-fluid" style="max-width: 300px; width: 100%; height: auto;">
                            </div>

                            <div class="text-muted px-3 mb-3" style="text-align: left;">
                                Please remove the concentrations which do not collapse well or exhibit multimodal distribution and recompute the critical concentration by repeating the procedure above.
                            </div>

                            <hr class="my-4 border-secondary-subtle">

                            <!-- Critical Exponents Calculation Section -->
                            <div class="mb-2">
                                <h6 id="criticalExponentsTitle" style="text-align: left;">2.  Critical Exponents </h6>
                            </div>

                            <!-- Plots -->
                            <div class="d-flex flex-column flex-md-row justify-content-center align-items-center gap-3 my-3">
                                <img id="phiCriticalExponentPlot" src="" alt="Phi Critical Exponent Plot"
                                    class="img-fluid" style="max-width: 300px; width: 100%; height: auto;">
                                <img id="alphaCriticalExponentPlot" src="" alt="Alpha Critical Exponent Plot"
                                    class="img-fluid" style="max-width: 300px; width: 100%; height: auto;">
                            </div>

                            <h6 id="criticalExponentsResults" class="fw-bold text-muted text-center fs-5 my-2">
                                <!-- Results will be populated here -->
                            </h6>
                            <br>
                            <!-- <div id='criticalExponentsCommentary' class="text-muted text-center px-3 mb-3" style="text-align: left;">
                                For critical phenomena, <strong>φ = 1</strong> and <strong>α = 0</strong>, and for percolation, <strong>φ = 2</strong> and <strong>α = 1.5</strong>.
                            </div> -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer Section -->
        <footer class="fixed-bottom bg-light-transparent py-1">
            <div class="container text-center">
                <p class="small text-muted mb-0">If you use this tool, please, cite  <a href="https://www.sciencedirect.com/science/article/pii/S0022283625003602?via%3Dihub" target="_blank">Brezinova, M., Fuxreiter, M. and Vendruscolo, M., 2025. DropFit: Determination of the Critical Concentration for Protein Liquid-Liquid Phase Separation. Journal of Molecular Biology, p.169294.</a>.</p>
            </div>
        </footer>
        <!-- End of Footer Section -->
        
        <!-- JavaScript -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
            $(document).ready(function () {

                // Cache commonly used elements
                var concentrationCalculationForm = $('#concentrationCalculationForm');
                var loadingBar = $('#loadingBar');
                var progressBar = $('#progressBar');
                var fileInput = $('#fileInput');
                var autoKSelectCheckbox = $('#autoKSelect');
                var manualKSelection = $('#manualKSelection');
                var manualKLabel = $('#manualKLabel');

                var concentrationPlotContainer = $('#concentrationPlotContainer');
                var concentrationValueContainer = $('#concentrationValueContainer');
                var collapsePlotContainer = $('#collapsePlotContainer');
                var concentrationChoiceContainer = $('#concentrationChoiceContainer');
            
                var errorMessage = $('#errorMessage');

                var showDataReportButtonContainer = $('#showDataReportButtonContainer');
                var dataFitnessReportContainer = $('#dataFitnessReportContainer');
                var dataReportResults = $('#dataReportResults');
                var sanityCheckWarnings = $('#sanityCheckWarnings');
                var dataFitnessReportSpinner = $('#dataFitnessReportSpinner');

                var checkboxStates = {};
                var criticalConcentration = 0;
                var maxConcentration = 0;
                var kToConsider = [];
                var concentrationsUsage = {};

                // Event listener for form submission. Run critical concentration calculation.
                concentrationCalculationForm.submit(function (event) {
                    event.preventDefault();

                    // Empty containers
                    emptyResultsContainers();
                    emptyDataReportResults();
                    resetConcentrationChoiceForDataReportResults();

                    // Create formData object
                    var formData = new FormData(this);


                    // errorMessage.empty().addClass('d-none');

                    let wasDataFitnessReportContaineVisible = dataFitnessReportContainer.is(':visible');
                    if(wasDataFitnessReportContaineVisible){
                        dataFitnessReportContainer.hide();
                    }

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
                            // DEBUGGING
                            // console.log(response);

                            // If the calculations were successful and outputs produced, show them
                            if (response.results_and_metadata_for_frontend && response.concentration_plot && response.collapse_plot) {
                                concentrationsUsage = response.results_and_metadata_for_frontend['concentrations_usage'];
                                kToConsider = response.results_and_metadata_for_frontend['k_to_consider'];
                                criticalConcentrationExpression = response.results_and_metadata_for_frontend['critical_concentration_expression']
                                criticalConcentrationMean = response.results_and_metadata_for_frontend['critical_concentration_mean']
                                
                                criticalConcentration = criticalConcentrationMean

                                updateConcentrationPlotsAndValue(response.concentration_plot,criticalConcentrationExpression, response.collapse_plot, concentrationsUsage);

                                const concentrationsUsed = Object.keys(concentrationsUsage)
                                    .filter(key => concentrationsUsage[key])          // keep only used (true)
                                    .map(key => parseFloat(key));                    // convert keys to numbers
                                maxConcentration = Math.max(...concentrationsUsed);

                                concentrationPlotContainer.show();
                                concentrationValueContainer.show();
                                concentrationChoiceContainer.show();
                                collapsePlotContainer.show();

                                showDataReportButtonContainer.show();

                                saveCheckboxState();
                                $('#results')[0].scrollIntoView({ behavior: 'smooth' });

                                loadingBar.hide(); // Hide the loading bar after the plot is generated
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
                            loadingBar.hide();
                            progressBar.css('width', 0 + '%').attr('aria-valuenow', 0);
                            errorMessage.html("Unknown error. Check your file and try again later.");
                            errorMessage.removeClass('d-none');
                        }
                    });
                });

                // Define what should happen if user clicks regenerate button (with updated concentrations selected). 
                // Run new critical concentration calculation.
                $('#regenerateButton').click(function () {
                    
                    // Create dataToSend object
                    var dataToSend = new FormData(concentrationCalculationForm[0]);

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

                    // Append checked values to dataToSend
                    dataToSend.append('concentrationsToOmit', concentrationsToOmitString);

                    hideContainerBodyContent();
                    errorMessage.empty().addClass('d-none');

                    let wasDataFitnessReportContaineVisible = dataFitnessReportContainer.is(':visible');
                    if(wasDataFitnessReportContaineVisible){
                        dataFitnessReportContainer.hide();
                    }

                    // AJAX request
                    $.ajax({
                        url: "/index.php/dropfit/getcriticalconcentration",
                        type: 'POST',
                        data: dataToSend,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function (response) {
                            if (response.results_and_metadata_for_frontend && response.concentration_plot && response.collapse_plot) {
                                concentrationsUsage = response.results_and_metadata_for_frontend['concentrations_usage'];
                                kToConsider = response.results_and_metadata_for_frontend['k_to_consider'];
                                criticalConcentrationExpression = response.results_and_metadata_for_frontend['critical_concentration_expression']
                                criticalConcentrationMean = response.results_and_metadata_for_frontend['critical_concentration_mean']
                                
                                criticalConcentration = criticalConcentrationMean

                                updateConcentrationPlotsAndValue(response.concentration_plot,criticalConcentrationExpression, response.collapse_plot, concentrationsUsage);
                                
                                const concentrationsUsed = Object.keys(concentrationsUsage)
                                    .filter(key => concentrationsUsage[key])          // keep only used (true)
                                    .map(key => parseFloat(key));                    // convert keys to numbers
                                maxConcentration = Math.max(...concentrationsUsed);

                                showContainerBodyContent();
                                saveCheckboxState();

                                $('#results')[0].scrollIntoView({ behavior: 'smooth' });
                                // Clean also data fitness report results and keep the dataFitnessReportContainer hidden
                                emptyDataReportResults();
                            } else if (response.error) {
                                errorMessage.html("Regeneration did not work due to error: " + response.error);
                                errorMessage.removeClass('d-none');
                                errorMessage[0].scrollIntoView({ behavior: 'smooth' });
                                showContainerBodyContent();
                                if(wasDataFitnessReportContaineVisible){
                                    dataFitnessReportContainer.show();
                                }
                                retrieveCheckboxState();
                            } else {
                                errorMessage.html("Regeneration did not work. " + "Unknown error. Check your file and try again later.");
                                errorMessage.removeClass('d-none');
                                errorMessage[0].scrollIntoView({ behavior: 'smooth' });
                                showContainerBodyContent();
                                if(wasDataFitnessReportContaineVisible){
                                    dataFitnessReportContainer.show();
                                }
                                retrieveCheckboxState();
                            }
                        },
                        error: function () {
                            errorMessage.html("Regeneration did not work. " + "Unknown error. Check your file and try again later.");
                            errorMessage.removeClass('d-none');
                            errorMessage[0].scrollIntoView({ behavior: 'smooth' });
                            showContainerBodyContent();
                            if(wasDataFitnessReportContaineVisible){
                                dataFitnessReportContainer.show();
                            }
                            retrieveCheckboxState();
                        }
                    });

                })

                $('#getDataFitnessReportButton').click(function () {

                    // Determine which critical concentration to use
                    var criticalConcentrationToUse = 0;
                    if($('#autoCriticalConcentration').is(':checked')){
                        criticalConcentrationToUse = criticalConcentration;
                    } else if ($('#dataFitnessReportCriticalConcentrationInput').val() !== '') {
                        criticalConcentrationToUse = $('#dataFitnessReportCriticalConcentrationInput').val();
                    } else {
                        errorMessage.html("Error with the critical concentration. Please, check the value and try again.");
                        errorMessage.removeClass('d-none');
                        errorMessage[0].scrollIntoView({ behavior: 'smooth'})
                        return;
                    }

                    // Create dataToSend object
                    var dataToSend = new FormData(concentrationCalculationForm[0]);

                    // Append critical concentration value to be sent to the script
                    dataToSend.append('criticalConcentration', criticalConcentrationToUse);

                    // Append concentration usage
                    dataToSend.append('concentrationsUsage', JSON.stringify(concentrationsUsage));
                    // Append ks to use in the calculations
                    dataToSend.append('kToConsider',kToConsider);

                    errorMessage.empty().addClass('d-none');
                    emptyDataReportResults();
                    dataFitnessReportSpinner.show();

                    // AJAX request
                    $.ajax({
                        url: "/index.php/dropfit/getdatafitnessreport",
                        type: 'POST',
                        data: dataToSend,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            console.log(response);
                            if (response.sanity_check_warnings && response.kdes_plot && response.concentrations_collapse_plot && response.alpha_critical_exponent_plot && response.phi_critical_exponent_plot && response.critical_exponents) {
                                const kdesPlotSrc = "data:image/png;base64," + response.kdes_plot;
                                const concentrationsCollapsePlotSrc = "data:image/png;base64," + response.concentrations_collapse_plot;                      
                                const phiCriticalExponentPlotSrc = "data:image/png;base64," + response.phi_critical_exponent_plot ;
                                const alphaCriticalExponentPlotSrc = "data:image/png;base64," + response.alpha_critical_exponent_plot;

                                if (response.sanity_check_warnings.length > 0){
                                    console.log('warnings sent');
                                    const warningMessageModelValidation = $('#warningMessageModelValidation');
                                    const sanityCheckWarnings = $('#sanityCheckWarnings');
                                    sanityCheckWarnings.hide();
                                    // Clear previous content
                                    sanityCheckWarnings.empty();
                                    response.sanity_check_warnings.forEach(item => {
                                        sanityCheckWarnings.append(`<li>${item}</li>`);
                                    });
                                    sanityCheckWarnings.show();
                                    warningMessageModelValidation.show();
                                }

                                $('#kdesPlot').attr('src', kdesPlotSrc);
                                $('#concentrationsCollapsePlot').attr('src', concentrationsCollapsePlotSrc );
                                $('#phiCriticalExponentPlot').attr('src', phiCriticalExponentPlotSrc);
                                $('#alphaCriticalExponentPlot').attr('src', alphaCriticalExponentPlotSrc);

                                const criticalExponentsResultsValue = `<strong>φ = ${response.critical_exponents.phi_critical_exponent_expression}</strong><br>
                                    <strong>α = ${response.critical_exponents.alpha_critical_exponent_expression}</strong>`;


                                $("#criticalExponentsResults").html(criticalExponentsResultsValue);

                                // Show the container
                                dataReportResults.show();
                                dataReportResults[0].scrollIntoView({ behavior: 'smooth' });
                                dataFitnessReportSpinner.hide();
                            } else if (response.error) {
                                dataFitnessReportSpinner.hide();
                                errorMessage.html(response.error);
                                errorMessage.removeClass('d-none');
                                errorMessage[0].scrollIntoView({ behavior: 'smooth' });
                            } else {
                                dataFitnessReportSpinner.hide();
                                errorMessage.html("Unknown error. Check your file and try again later.");
                                errorMessage.removeClass('d-none');
                                errorMessage[0].scrollIntoView({ behavior: 'smooth' });
                            }
                        },
                        error: function(errMsg) {
                            alert(errMsg);
                        }
                        // error: function () {
                        //     console.log(formData);
                        //     loadingBar.hide();
                        //     progressBar.css('width', 0 + '%').attr('aria-valuenow', 0);
                        //     errorMessage.html("JS Unknown error. Check your file and try again later.");
                        //     errorMessage.removeClass('d-none');
                        // }
                    });
                });

                $('#showDataReportButton').click(function () {
                    dataFitnessReportContainer.show();
                    dataFitnessReportContainer[0].scrollIntoView({ behavior: 'smooth' });
                });

                $('input[name="criticalConcentrationOption"]').change(function () {
                    const isManualSelected = $('#manualCriticalConcentration').is(':checked');
                    $('#dataFitnessReportCriticalConcentrationInput').prop('disabled', !isManualSelected);
                    if(!isManualSelected){
                        $("#dataFitnessReportValidationConcentrationInvalidValueMessage").hide();
                    }
                    checkdataFitnessReportConcentrationInputs();
                });

                // Event listener for autoKSelect checkbox change
                autoKSelectCheckbox.change(function () {
                    var autoKSelect = this.checked;
                    toggleManualKSelection(autoKSelect);
                    checkKInputs();
                });

                // Event listener for erase button
                $('#eraseKButton').click(function () {
                    // Clear all input fields
                    manualKSelection.find('input[type="number"]').val('');
                });

                // Function to toggle manual selection section
                function toggleManualKSelection(disabled) {
                    if (disabled) {
                        manualKSelection.addClass('disabled-section');
                        manualKLabel.addClass('disabled-label');
                        manualKSelection.find('input, button').prop('disabled', true);
                        $("#validationManualKMessage").hide();
                    } else {
                        manualKSelection.removeClass('disabled-section');
                        manualKLabel.removeClass('disabled-label');
                        manualKSelection.find('input, button').prop('disabled', false);
                        manualKSelection.find('input[type="number"]').removeClass('grey-text');
                    }
                }

                // Function to empty results containers
                function emptyResultsContainers(){
                    // Empty concentration plot sources
                    $('#concentrationPlot').attr('src', "");
                    $('#concentrationPlotDownloadButton').attr('href', "");
                    concentrationPlotContainer.hide();

                    // Empty concentration value
                    $('#concentrationValue').text("");
                    concentrationValueContainer.hide();

                    // Empty concentrations to leave out
                    $('#concentrationsToLeaveOut').empty();
                    concentrationChoiceContainer.hide();

                    // Empty collapse plot sources
                    $('#collapsePlot').attr('src', "");
                    $('#collapsePlotDownloadButton').attr('href', "");
                    collapsePlotContainer.hide();

                    showDataReportButtonContainer.hide();

                    // Empty error message
                    errorMessage.empty().addClass('d-none');
                }

                function emptyDataReportResults(){
                    dataReportResults.hide();
                    sanityCheckWarnings.empty();
                    $('#kdesPlot').attr('src', "");
                    $('#concentrationsCollapsePlot').attr('src', "");
                    $('#phiCriticalExponentPlot').attr('src', "");
                    $('#alphaCriticalExponentPlot').attr('src', "");
                }

                function resetConcentrationChoiceForDataReportResults(){
                    $('#dataFitnessReportCriticalConcentrationInput').val('');
                    $('#autoCriticalConcentration').prop('checked', true);
                    $('#manualCriticalConcentration').prop('checked', false);
                    $('#dataFitnessReportCriticalConcentrationInput').prop('disabled', true);
                }

                // Hide spinners and display the new plots and value
                function showContainerBodyContent(){
                    $('.spinner').hide();
                    $('#concentrationPlot').show();
                    $('#concentrationPlotDownloadButton').show();
                    $('#concentrationValue').show();
                    $('#collapsePlot').show();
                    $('#collapsePlotDownloadButton').show();
                    showDataReportButtonContainer.show();
                }

                // Function to hide container body content. Show spinners and hide the plots and value
                function hideContainerBodyContent(){
                    // Hide spinners and display the new plots and value
                    $('.spinner').show();
                    $('#concentrationPlot').hide();
                    $('#concentrationPlotDownloadButton').hide();
                    $('#concentrationValue').hide();
                    $('#collapsePlot').hide();
                    $('#collapsePlotDownloadButton').hide();
                    showDataReportButtonContainer.hide();
                }

                function updateConcentrationPlotsAndValue(concentrationPlot,concentration, collapsePlot, concentrationsUsage){
                    const concentrationPlotSrc = "data:image/png;base64," + concentrationPlot;
                    const collapsePlotSrc = "data:image/png;base64," + collapsePlot;

                    // Update plots and concentration value
                    $('#concentrationValue').text(concentration);
                    $('#concentrationPlot').attr('src', concentrationPlotSrc);
                    $('#concentrationPlotDownloadButton').attr('href', concentrationPlotSrc);
                    $('#collapsePlot').attr('src', collapsePlotSrc);
                    $('#collapsePlotDownloadButton').attr('href', collapsePlotSrc);
                    createConcentrationCheckboxes($("#concentrationsToLeaveOut"), concentrationsUsage);

                }

                function checkKInputs() {
                    var fileSelected = fileInput.val();
                    var autoKSelected = autoKSelectCheckbox.prop('checked');
                    var inputsPopulated = false;
                
                    // If the auto K is not selected, check also if at least 1 k value has been inputted
                    if(!autoKSelected){
                        // Check if any input of type number is populated
                        manualKSelection.find('input[type="number"]').each(function() {
                            if ($(this).val().trim() !== '') {
                                inputsPopulated = true;
                                return false; // Exit the loop early if any input is populated
                            }
                        });
                    }
                
                    if(!inputsPopulated && !autoKSelected){
                        $("#atLeast1InputManualKMessage").show();
                    } else {
                        $("#atLeast1InputManualKMessage").hide();
                    }

                    // Enable processButton if any input is populated or autoKSelectCheckbox is checked and file is selected
                    if ((inputsPopulated || autoKSelected) && fileSelected) {
                        $('#processButton').prop('disabled', false);
                    } else {
                        $('#processButton').prop('disabled', true);
                    }
                }

                function checkdataFitnessReportConcentrationInputs() {
                    const isAutoSelected = $('#autoCriticalConcentration').is(':checked');
                    const isValueProvided = $('#dataFitnessReportCriticalConcentrationInput').val() !== '';
                    
                    if (isAutoSelected || isValueProvided) {
                        $('#getDataFitnessReportButton').prop('disabled', false);
                        $("#dataFitnessReportValidationConcentrationEmptyMessage").hide();
                    } else {
                        $('#getDataFitnessReportButton').prop('disabled', true);
                        $("#dataFitnessReportValidationConcentrationEmptyMessage").show();
                    }
                }
                    
                // Check validity
                function checkValidity(id, isKsField) {
                    var input = document.querySelector('#' + id);
                    var value = parseFloat(input.value);

                    if(isKsField){
                        if (!input.validity.valid){
                            input.value = '';
                            $("#validationManualKMessage").show();
                        } else {
                            $("#validationManualKMessage").hide();
                        }
                    } else {
                        // COMMENT OUT IF YOU DO NOT WANT TO IMPOSE CRITICAL CONCENTRATION
                        // TO BE LIMITED ONLY TO VALUES LARGER THAN CONCENTRATIONS USED
                        if (!input.validity.valid || isNaN(value) || value <= maxConcentration) {
                        // UNCOMMENT IF YOU DO NOT WANT TO IMPOSE RESTRICTIONS ON THE CRITICAL CONCENTRATION
                        // if (!input.validity.valid || isNaN(value)) {
                            input.value = '';
                            $("#dataFitnessReportValidationConcentrationInvalidValueMessage").show();
                        } else {
                            $("#dataFitnessReportValidationConcentrationInvalidValueMessage").hide();
                        }
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

                // Event listener for file input change
                fileInput.change(function () {
                    var fileName = $(this).val().split('\\').pop();
                    $(this).next('.custom-file-label').html(fileName);
                    // Check all inputs
                    checkKInputs();

                });

                $('input[name^="k"]').change(function () {
                    checkValidity(this.id, true);
                    checkKInputs();
                });

                // Initially disable manual selection if autoKSelect is checked
                toggleManualKSelection(autoKSelectCheckbox.prop('checked'));

                $('#dataFitnessReportCriticalConcentrationInput').change(function () {
                    checkValidity(this.id, false);
                    checkdataFitnessReportConcentrationInputs();
                });
            });
        </script>
    </body>
    </html>

<?php
// this is a concrete5 way to make the main part editable
$a = new Area('Footer');
$a->display($c);