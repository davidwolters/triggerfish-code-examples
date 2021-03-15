<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Incheckningssystem</title>
	<link rel="stylesheet" href="<?php echo plugins_url(); ?>/helloworld-crm/interface/qr/style.css?v=3">

	<script src="<?php echo plugins_url(); ?>/helloworld-crm/interface/node_modules/gentelella/vendors/jquery/dist/jquery.min.js"></script>
	<script src="<?php echo plugins_url(); ?>/helloworld-crm/interface/qr/lib/qrparser.js"></script>

	<script src="<?php echo plugins_url(); ?>/helloworld-crm/interface/qr/View.js?v=4"></script>
	<?php include 'Model.php'; ?>
	<script src="<?php echo plugins_url(); ?>/helloworld-crm/interface/qr/TrelloManager.js?v=3"></script>

	<script src="<?php echo plugins_url(); ?>/helloworld-crm/interface/qr/QRScanner.js?v=1"></script>

	<!-- Client.js -->
	<script src="https://trello.com/1/client.js?key=0258080a154c252bda276e8dd3c4099a"></script>

	<!-- Font Awesome -->
	<script src="https://use.fontawesome.com/ff67e30cf0.js"></script>

</head>
<body>




<div class="action-cancel"></div>

<div class="application-wrapper">
	<div id="video-container">

		<video id="camera-stream" autoplay playsinline></video>
	</div>

	<canvas id="output-canvas"></canvas>

	<div class="name-label">
		<div class="name-label-name"></div>
		<div class="name-label-checkout"></div>
	</div>


	<div class="bottom-bar">
		<div class="action-button action-waiting">
			Väntar...
		</div>
	</div>


	<div class="popup">
		<div class="content-wrapper">
			<h1 class="error">Oj då!</h1>
			<p id="error-desc">Hej</p>
		</div>
		<div class="button error-close"><span>Stäng</span></div>
	</div>

	<div class="popup-success">
		<div class="content-wrapper">
			<h1 class="success">Oj då!</h1>
			<p id="success-desc">Hej</p>
		</div>
		<div class="button success-close"><span>Stäng</span></div>
	</div>

</div>

<?php include 'app.php'; ?>

</body>
</html>