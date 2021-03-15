/**
 * Created by david on 6/3/18.
 */

/*jshint esversion: 6 */
/*globals $:false */
/*globals console:false */
/*globals View:false */
/*globals TrelloManager:false */
/*globals Model:false */
/*globals qrcode:false */


/**
 * This class is responsible for starting the camera,
 * and scanning every 500ms for a QR code.
 *
 * To use this class:
 *  - Create an instance
 *  - Assign the handleValidQROutput method to a custom handler method.
 *  - Call the startQRStream function.
 */
class QRScanner {

	/**
	 * Constructor for QRScanner.
	 *
	 * @param outputCanvasID			The HTML id of the canvas which will hold the images being taken of the video element.
	 * @param videoElementID			The HTML id of the video element which will show the stream.
	 */
	constructor(outputCanvasID, videoElementID) {

		this.outputCanvasID = outputCanvasID;
		this.videoElementID = videoElementID;

		// If we have started the stream yet.
		this.streamStarted = false;

		// This is used to take images of the video.
		this.context = undefined;

		// The HTML video element.
		this.videoElement = undefined;

		// The HTML canvas element.
		this.captureCanvas = undefined;

		this.scanInterval = 250;
	}

	/**
	 * This method starts the stream,
	 * and starts looking for QR codes.
	 */
	startQRStream() {

		// Initialize the canvas.
		this.initCanvas(800, 600);

		let instance = this;

		// This callback will be called anytime the qrcode.decode method is called.
		qrcode.callback = function(data) {
			instance.onQROutput(data);
		};

		// Start the stream.
		instance.startStream();
	}

	initCanvas(w, h) {
		this.captureCanvas = document.getElementById(this.outputCanvasID);
		this.captureCanvas.style.width = w + "px";
		this.captureCanvas.style.height = h + "px";
		this.captureCanvas.width = w;
		this.captureCanvas.height = h;
		this.context = this.captureCanvas.getContext("2d");
		this.context.clearRect(0, 0, w, h);
	}

	/**
	 * This method starts the video stream.
	 */
	startStream() {

		// These constraints make sure we select the rear camera on mobile devices.
		let constraints = {
			audio: false,
			video: {
				facingMode: 'environment',
			}
		};

		let instance = this;

		this.videoElement = document.getElementById(this.videoElementID);


		// Start the stream. This does not play the stream, this is done later in streamStartedSuccess
		if (navigator.mediaDevices.getUserMedia) {
			navigator.mediaDevices.getUserMedia(constraints).then(function (stream) {
				instance.streamStartedSuccess(stream);
			}).catch(function (e) {
				instance.streamStartedError(e);
			});
		} else {
			let getUserMedia = navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.getUserMedia;
			getUserMedia(constraints).then(function (stream) {
				instance.streamStartedSuccess(stream);
			}).catch(function (e) {
				instance.streamStartedError(e);
			});
		}
	}

	/**
	 * This method is called when the stream has started, it starts the stream,
	 * and starts looking for the QR code.
	 *
	 * @param stream	The stream returned from the callback from getUserMedia.
	 */
	streamStartedSuccess(stream) {

		this.videoElement.srcObject = stream;

		this.videoElement.play();

		this.streamStarted = true;

		let instance = this;

		// Start looking for QR codes.
		setTimeout(function () {
			instance.lookForQRCode();
		}, this.scanInterval);
	}

	/**
	 * This method is called if there was an error starting the stream.
	 *
	 * @param error		The error.
	 */
	streamStartedError(error) {
		View.displayError(error);
	}

	/**
	 * This method captures whatever the camera is showing and
	 * returns the url data from it.
	 *
	 * @return The data, or null if something went wrong.
	 */
	getContentData() {


		try {
			this.context.drawImage(this.videoElement, 0, 0);

			return this.captureCanvas.toDataURL('image/png');

		} catch (e) {
			View.displayError(e);
		}

		return null;
	}

	/**
	 * This method decodes the data from the getContentData function.
	 * The result will be passed onto onQROutput.
	 * @param data The data from the getContentData function.
	 */
	decodeData(data) {
		try {
			qrcode.decode(data);
		} catch (e) {
			View.displayError(e);
		}
	}

	/**
	 * This method looks for a QR code.
	 * The result is passed onto onQRoutput.
	 * It reruns itself every 500ms, as long as the stream has started.
	 */
	lookForQRCode() {
		if (this.streamStarted) {

			let instance = this;

			let data = this.getContentData();
			if (data) {
				this.decodeData(data);
			}

			setTimeout(function () {
				instance.lookForQRCode();
			}, this.scanInterval);
		}
	}

	/**
	 * This method is called everytime a scan is made,
	 * so even though no code was scanned this will be called.
	 * If no code was scanned, or there were any other errors the qrData
	 * will start with "error".
	 * If a valid QR code was found, handleValidQROutput is called.
	 *
	 * @param qrData	The result from qrcode.decode
	 */
	onQROutput(qrData) {

		// We don't want to do anything if there is any errors.
		if (qrData.startsWith('error'))
			return;


		this.handleValidQROutput(qrData);
	}


	/**
	 * This method will be called when we have found a QR code.
	 * @param qrData
	 */
	handleValidQROutput(qrData) {}

}
