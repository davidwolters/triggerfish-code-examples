

/*jshint esversion: 6 */
/*global $:false */
/*global console:false */

// Methods that manipulate the DOM. //
class View {

	// Sets the application wrapper to display:none or display:block.
	static setViewVisible(visible) {
		let displayStatus = (visible) ? 'block' : 'none';
		$('.application-wrapper').css('display', displayStatus);
	}


	// Displays an error via a popup. //
	static displayError(description) {
		console.log(description);
		$('.popup').css('display', 'block');
		$('#error-desc').html(description);
	}

	// Displays an error via a popup. //
	static displaySuccess(name, msg = null) {
		$('.popup-success').css('display', 'block');
		$('h1.success').html(name + ' är nu registrerad!')
		$('#success-desc').html(msg);
	}

	// Sets the action button to show that an action is avaliable. //
	static actionAvailable(checkedIn) {
		let text = checkedIn ? 'Checka Ut' : 'Checka In';
		let btnClass = checkedIn ? 'action-check-out' : 'action-check-in';
		let btn = $('.action-button');
		btn.removeClass('waiting');
		btn.addClass(btnClass);
		btn.html(text);
	}

	// This method shows the default action button. //
	static defaultActionButton(buttonText='Väntar...') {
		View.resetActionButton();

		let btn = $('.action-button');
		btn.addClass('action-waiting');
		btn.html(buttonText);
	}

	// This method removes all style classes from the action button. //
	static resetActionButton() {
		let btn = $('.action-button');
		btn.removeClass('action-check-in');
		btn.removeClass('action-check-out');
		btn.removeClass('action-waiting');
		btn.removeClass('action-done');
	}

	// Sets the action button to show that it is working. //
	static pendingActionButton() {
		View.defaultActionButton('Arbetar...');
	}

	// Shows the action button and shows that it is scanning a qr code. //
	static scanningActionButton() {
		View.showActionButton();
		View.defaultActionButton('Skannar');
	}

	// Shows a completion message, and fades out the action button after an interval. //
	static completedActionButton() {
		View.resetActionButton();
		let btn = $('.action-button');
		btn.addClass('action-done');
		btn.html('Klar!');
		View.hideActionButton(500);

	}

	// Hides the action button with a fade after an interval. //
	static hideActionButton(interval=0) {
		window.setTimeout(() => {
			$('.action-button').fadeOut();
		}, interval);
	}

	// Shows the action button with a fade after an interval. //
	static showActionButton(interval=0) {
		window.setTimeout(() => {
			$('.action-button').fadeIn();
		}, interval);
	}

	static nameLabel(show, userInfo={text:"",checkOut:""}) {
		let label = $('.name-label');

		if (show) {
			label.fadeIn('fast');
		} else {
			label.fadeOut('fast');
		}

		label.find('.name-label-name').html(userInfo.text);
		label.find('.name-label-checkout').html(userInfo.checkOut);

	}
}

