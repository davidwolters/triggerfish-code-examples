<script name="app">

///////
// TEST CARD ID: 5a9974260c71ecb17489c579 //
///////


/*jshint esversion: 6 */
/*globals $:false */
/*globals console:false */
/*globals View:false */
/*globals TrelloManager:false */
/*globals Model:false */


(function () {
	'use strict';

	// If we are currently dealing with a scanned QR code. //
	let working = false;

	// The ticket data. //
	let currentTicket = '';

	// If the current card is checked in. //
	let currentCheckedIn = false;

	// The content of the last scanned QR code. //
	let lastScanned = '';

	// The time at which the last QR code was scanned. //
	let lastScannedTimeStamp = -1;

	// The minimum interval after which the same QR code can be scanned twice in a row. To avoid accidentally scanning twice. //
	const minScanInterval = 120;








	$(document).ready(e => {


		// ======= CAMERA STUFF ======= //
		let scanner = new QRScanner('output-canvas', 'camera-stream');


		// The handler method for when a QR code has been scanned.
		scanner.handleValidQROutput =
			content => {

			// Make sure the QR code is valid.
			if (!validQR(content)) {
				View.displayError('QR Koden är i fel format!');
				return;
			}

			// Make sure we are not working & we can scan the current QR code.
			if (!working && canScan(content)) {

				// We need to fetch the info from the UID.
				$.ajax({
					url: '<?php echo home_url('wp-json/hwcrm/v2/ticket/info'); ?>',
					type: 'GET',
    				data: {uid: content} ,
					success: (data) => {

						currentTicket = {
							name: data.name,
							cardID: data.trello_id,
							mobiles: data.mobiles,
							checkOut: data.check_out,
						};

						// Show the header with the check-out info & name.
						displayTicketData();

						// Get the label status from trello.
						fetchStatusFromTrello(content);
					},
					error: (data) => {
						View.displayError(JSON.stringify(data));
					}
				});
			}
		};

		// Start scanning.
		scanner.startQRStream();




		// ======= ======= ======= ======= //

		// ======= JQUERY STUFF ======= //
		$('.error-close').on('click', function () {
			$('.popup').css('display', 'none');
		});

		$('.success-close').on('click', function () {
			$('.popup-success').css('display', 'none');
		});

		$('.action-button').on('click', function () {

			if (working) {
				View.pendingActionButton();
				TrelloManager.switchLabels(currentTicket.cardID, () => {
					working = false;

					// Hide the action button. //
					View.completedActionButton();

					// Hide the name label. //
					View.nameLabel(false);

					// Now we need to send the message to their parents. //
					Model.sendConfirmationMessage(currentTicket.name, !currentCheckedIn, currentTicket.mobiles, s => {
						View.displaySuccess(currentTicket.name, s.success)
					}, e => {
						View.displayError(e.error);
					});

				}, () => {
					View.displayError('Det gick inte att checka in/ut denna deltagare.');
				});
			}
		});


		$('.action-cancel').on('click', function () {
			if (working) {
				working = false;
				View.hideActionButton(0);
			}
		});


		// ======= ======= ======= ======= //


		// ======= TRELLO STUFF ======= //

		// Starts the scan, after recieveing information about the ticket from Hello World!.
		let fetchStatusFromTrello = (content) => {
			lastScanned = content;
			lastScannedTimeStamp = new Date().getUTCSeconds();
			working = true;



			// Set the actionButton to scanning.
			View.scanningActionButton();

			// Get the card status.
			TrelloManager.getCardStatus(currentTicket.cardID, checkedIn => {

				currentCheckedIn = checkedIn;
				View.actionAvailable(checkedIn);
			}, () => {
				View.hideActionButton();

				View.displayError('Det gick inte att hitta detta kort!');
			});
		};

		// Display the header with name & checkOut status.
		let displayTicketData = () => {
			View.nameLabel(true, {text: currentTicket.name, checkOut: currentTicket.checkOut});
		};


		let authorized = (token) => {
			// Set the token.
			window.Trello.setToken(token);

			View.setViewVisible(true);
		};

		let notAuthorized = () => {
			$('body').html('<h2>Oj då! Appen är inte kopplat till något trello konto, vänligen kontakta en administratör!</h2>');
		};

		Model.getToken(authorized, notAuthorized);



	  // ======= ======= ======= ======= //
	});


	// If the same QR code is scanned twice in a row, make sure enough time has passed since the last scan. //
	function canScan(content) {
		let d = new Date();
		return content !== lastScanned || (d.getUTCSeconds() - lastScannedTimeStamp) > minScanInterval;
	}

	// Make sure the QR code is a valid md5 hash.
	function validQR(content) {
		return content.length === 32;
	}

}());



</script>