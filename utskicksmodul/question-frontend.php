<!-- TODO: Konstiga redigeringsknappar. -->
<?php

// Get the event ID.
$event_ID = false;
if ( isset( $_GET[ 'hwcrm_arg' ] ) ) {
	$event_ID = intval( $_GET[ 'hwcrm_arg' ] );
}

// Get the event type. //
$t = get_the_terms( $event_ID, 'hwcrm_event_type' )[ 0 ];

// Get the event name.
$event_name = get_post_meta( $event_ID, '_title', true );

// This function returns the option key of a mailing based on the event type and the index of the mailing.
function opt( $t, $number ) {
	return 'HWCRM_MAILING_' . $t->term_id . '_' . $number;
}

// If we are saving a mailing.
if ( isset( $_POST[ 'action' ] ) and $_POST[ 'action' ] == "save-mailing" ) {

	// Get the POST data.
	$content = $_POST[ 'mailing-content' ];
	$title   = $_POST[ 'mailing-title' ];
	$notes   = $_POST[ 'mailing-notes' ];
	$name    = $_POST[ 'mailing-name' ];

	$opt_base = opt( $t, $_POST[ 'mailing-option' ] );

	$title_opt = $opt_base . '_TITLE';
	$notes_opt = $opt_base . '_NOTES';
	$name_opt  = $opt_base . '_NAME';

	// Update the previous option.
	update_option( $opt_base, $content );
	update_option( $title_opt, $title );
	update_option( $name_opt, $name );
	update_option( $notes_opt, $notes );

}

if ( isset( $_POST[ 'disable-mailing' ] ) ) {
	$disable     = $_POST[ 'mailing-disable' ] === 'yes';
	$opt_base    = opt( $t, $_POST[ 'mailing-option' ] );
	$disable_opt = $opt_base . '_DISABLE';
	update_option( $disable_opt, 'yes' );
}

// If we are adding a new mailing.
if ( isset( $_POST[ 'add-mailing' ] ) ) {

	// The option key containing the number of mailings for this event type.
	$max_option = 'HWCRM_MAILING_' . $t->term_id . '_NUM';
	$opt_num    = get_option( $max_option );

	// The index of the next mailing.
	$next = ( $opt_num !== false ) ? intval( $opt_num ) + 1 : 0;

	// If this is an email or an SMS.
	$type = ( isset( $_POST[ 'add-email' ] ) or isset( $_POST[ 'add-leader-email' ] ) ) ? 'email' : 'sms';

	// If this is a leader mailing we need to save this.
	if ( isset( $_POST[ 'add-leader-email' ] ) or isset( $_POST[ 'add-leader-sms' ] ) ) {
		update_option( opt( $t, $next ) . '_LEADER', true );
	}

	// Create the mailing.
	update_option( opt( $t, $next ), '' );
	update_option( opt( $t, $next ) . '_TITLE', '' );
	update_option( opt( $t, $next ) . '_TYPE', $type );
	update_option( opt( $t, $next ) . '_NAME', '' );
	update_option( $max_option, $next );
}

// Get all mailings for the event type.
$mailings = array();
$i        = 0;
$m        = get_option( opt( $t, $i ) );
while ( $m !== false ) {
	$mailings[] = array(
		'title' => get_option( opt( $t, $i ) . '_TITLE' ),
		'name'  => get_option( opt( $t, $i ) . '_NAME' ),
		'type'  => get_option( opt( $t, $i ) . '_TYPE' ),

		'index'      => $i,
		'opt'        => opt( $t, $i ),
		'last_sent'  => get_option( opt( $t, $i ) . "_$event_ID" . '_LAST_SENT' ),
		'send_count' => get_option( opt( $t, $i ) . "_$event_ID" . '_SEND_COUNT' ),
		'leader'     => get_option( opt( $t, $i ) . '_LEADER' ) !== false,
		'disable'    => get_option( opt( $t, $i ) . '_DISABLE' ) === 'yes',
	);

	$i++;
	$m = get_option( opt( $t, $i ) );
}

// These tags are used for mailings sent to
// participants / guardians.
$participant_tags = array(
	"%FNAME% - Mottagarens förnamn",
	"%EVENT% - Evenemangets titel",
	"%TICKET% - Biljettnamn",
	"%TRACK% - Spårnamn",
	"%PARTICIPANT% - Deltagarens namn",
	"%PARTICIPANT_FNAME% - Deltagarens förnamn",
	"%PRICE% - Pris",
	"%ID% - OrderID (internt)",
	"%ROUTE% - Vägbeskrivning",
	"%ROOM% - Rum",
	"%LEADERS% - Ledare",
	"%DATE% - Datum",
	"%TIME% - Tid",
	"%LOCATION% - Plats",
	"<strong>Tåg-taggar.</strong>",
	"%TRAIN_FROM_DATE% - Tåg hem datum",
	"%TRAIN_FROM_VEHICLE% - Tåg hem resemedel",
	"%TRAIN_FROM_NUMBER% - Tåg hem tågnummer",
	"%TRAIN_FROM_CAR% - Tåg hem vagn",
	"%TRAIN_FROM_POSITION% - Tåg hem plats",
	"%TRAIN_FROM_DEPARTURE_TIME% - Tåg hem avgång",
	"%TRAIN_FROM_ARRIVAL_TIME% - Tåg hem ankomst",
	"%TRAIN_FROM_STATION% - Tåg hem till station",
	"%TRAIN_FROM_STATION_FROM% - Tåg hem från station",
	"%TRAIN_FROM_INFO% - Tåg hem info",
	"%TRAIN_FROM_BOOKING_ID% - Tåg hem boknings ID",
	"",
	"%TRAIN_TO_DATE% - Tåg till datum",
	"%TRAIN_TO_VEHICLE% - Tåg till resemedel",
	"%TRAIN_TO_NUMBER% - Tåg till tågnummer",
	"%TRAIN_TO_CAR% - Tåg till vagn",
	"%TRAIN_TO_POSITION% - Tåg till plats",
	"%TRAIN_TO_ARRIVAL_TIME% - Tåg till ankomst",
	"%TRAIN_TO_DEPARTURE_TIME% - Tåg till avgång",
	"%TRAIN_TO_STATION% - Tåg till till station",
	"%TRAIN_TO_STATION_FROM% - Tåg till från station",
	"%TRAIN_TO_INFO% - Tåg till info",
	"%TRAIN_TO_BOOKING_ID% - Tåg till boknings ID",

);

// These tags are used for mailings sent to
// leaders, which only contain event specific
// data since leaders don't have bookings associated with them.
$leader_tags = array(
	"%FNAME% - Ledarens förnamn",
	"%EVENT% - Evenemangets titel",
	"%ROUTE% - Vägbeskrivning",
	"%DATE% - Datum",
	"%TIME% - Tid",
	"%LOCATION% - Plats",
);


?>

<?php include 'header.php'; ?>
<?php include 'sidebar.php'; ?>
<?php include 'topnav.php'; ?>


<!-- page content -->
<div class="right_col" role="main">
	<div class="col-md-12 col-sm-12 col-xs-12">
		<h1>Utskick för <?php echo $event_name; ?> (<?php echo $t->name; ?>) </h1>
	</div>

	<div class="col-xs-12">
		<!-- Send mailing form -->
		<div class="x_panel">
			<div class="x_title">
				<h2>Skicka SMS/E-post</h2>

				<ul class="nav navbar-right panel_toolbox">
					<li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a>
					</li>
				</ul>

				<div class="clearfix"></div>

			</div>
			<div class="x_content">
				<form method="post" class="form-horizontal form-label-left" id="u-send-form">
					<input type="hidden" value="<?php echo $event_ID; ?>" name="event_id">
					<input type="hidden" name="u-daycamp" value="yes"/>
					<input type="hidden" name="u-type" id="u-type" value=""/>
					<div class="form-group">
						<div class="col-sm-3 col-xs-12">
							<label>Utskick </label>
						</div>
						<div class="col-sm-9 col-xs-12">
							<select id="u-content" name="u-content">
								<option value="0">-- Välj utskick --</option>
								<?php foreach ( $mailings as $m ):
									if ( $m[ 'disable' ] ) {
										continue;
									}
									$leader = $m[ 'leader' ] ? ' data-leader="yes"' : '';
									?>
									<option id="<?php echo opt( $t, $m[ 'index' ] ); ?>"
											value="<?php echo opt( $t, $m[ 'index' ] ); ?>"
											data-type="<?php echo $m[ 'type' ]; ?>"
										<?php echo $leader; ?>>
										<?php echo $m[ 'title' ]; ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

					</div>
					<div id="u-filters">
						<div class="form-group">
							<div class="col-sm-3 col-xs-12">
								<label> Mottagare </label>
							</div>
							<div class="col-sm-9 col-xs-12">
								<input type="checkbox" name="u-rec-parent" id="u-rec-parent" checked/> Vårdnadshavare
								<br/>
								<input type="checkbox" name="u-rec-child" id="u-rec-child"/> Deltagare
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-3 col-xs-12">
								<label> Filter - Biljett </label> <br>
							</div>
							<div class="col-sm-9 col-xs-12 u-filter-ticket">
								<input type="checkbox"
									   class="u-all-button"
									   data-target="u-filter"
									   name="u-filter-all"
									   id="u-filter-all"
									   checked>
								<strong> Alla </strong><br/>

								<?php foreach ( HWCRM_Event::get_tickets_for_event( $event_ID ) as $ticket ): ?>
									<input type="checkbox"
										   class="u-filter u-all-group"
										   data-target-id="u-filter-all"
										   name="u-filter-<?php echo $ticket; ?>"
										   id="u-filter-day">
									<?php echo get_option( $ticket . '_name' ); ?> <br/>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="form-group">
							<div class="col-sm-3 col-xs-12">
								<label>Filter - Tåg (markera <i>Alla</i> för event som saknar tågplats.)</label>
							</div>
							<div class="col-sm-9 col-xs-12 u-filter-train">
								<input type="checkbox"
									   class="u-all-button"
									   data-target="u-train"
									   name="u-train-all"
									   id="u-train-all"
									   checked>
								<strong>Alla</strong><br/>

								<input type="checkbox"
									   class="u-train u-all-group"
									   data-target-id="u-train-all"
									   name="u-to-train-yes">
								Åker tåg till lägret
								<br/>

								<input type="checkbox"
									   class="u-train u-all-group"
									   name="u-to-train-no"
									   data-target-id="u-train-all">
								Åker EJ tåg till lägret <br/>

								<input type="checkbox"
									   class="u-train u-all-group"
									   name="u-from-train-yes"
									   data-target-id="u-train-all">
								Åker tåg hem <br/>

								<input type="checkbox"
									   class="u-train u-all-group"
									   name="u-from-train-no"
									   data-target-id="u-train-all">
								Åker EJ tåg hem <br/>


							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-3 col-xs-12">
								<label>Filter - Spår</label>
							</div>
							<div class="col-sm-9 col-xs-12 u-filter-track">
								<input type="checkbox"
									   name="u-track-all"
									   class="u-all-button"
									   data-target="u-track-check"
									   id="u-track-all"
									   checked>
								<strong>Alla</strong>
								<?php foreach ( HWCRM_Event::get_all_tracks_for_event( $event_ID, true ) as $track ):
									// Staff tracks that don't have any participants should not be included.
									if ( HWCRM_Helpers::is_staff_track( $track->name ) and $track->current_bookings <= 0 ) {
										continue;
									}
									?>

									<br/>
									<input type="checkbox"
										   class="u-track-check u-all-group"
										   data-target-id="u-track-all"
										   name="u-track-<?php echo $track->term_id; ?>"/>

									<?php echo $track->name; ?>

								<?php endforeach; ?>


							</div>

						</div>
					</div>
					<div class="form-group">
						<button class="btn btn-primary" data-action="send-mailing" data-form="send-form"
								name="send-mailing">Skicka
						</button>
					</div>
					<div id="send-error"></div>

				</form>
				<div class="result-csv" style="display:none">
					<h2>Utskicket skickades till följande:</h2>
					<p>Texten är i CSV format. För att spara den i ett excel-ark, kan du kopiera texten in i en fil med
						ändelsen <code>.csv</code>, och sedan importera den i excel.</p>
					<textarea id="csv" rows="10" style="width:100%" readonly></textarea>
				</div>

				<div id="mailing-count" style="display: none;">
					<h2>
						<span id="sent-mailings">0</span> / <span id="total-mailings">?</span> utskick skickade. <br/>
					</h2>
					<h2 class="danger">Misslyckade Utskick:</h2>
				</div>
				<pre id="failed-mailings" style="display: none;"></pre>
			</div>
		</div>
		<!-- END of Send mailing form -->

		<!-- Mailings -->
		<?php foreach ( $mailings as $m ):
			if ( $m[ 'disable' ] ) {
				continue;
			}

			$type_name       = $m[ 'type' ] === 'email' ? 'E-POST ' : 'SMS';
			$label_style     = $m[ 'type' ] === 'email' ? 'primary' : 'info';
			$leader          = $m[ 'leader' ] ? '<span class="label label-warning" style="color: white;">Ledar-utskick</span>' : '';
			$last_send_time  = $m[ 'last_sent' ] ? "Utskicket skickades {$m['last_sent']}" : 'Utskicket har ej skickats än';
			$last_send_count = $m[ 'send_count' ] ? " till {$m['send_count']} mottagare" : "";
			?>
			<form method="post" class="form-horizontal form-label-left collapsed">
				<div class="x_panel">
					<div class="x_title">
						<div class="col-sm-10">
							<h2><span class="label label-<?php echo $label_style; ?>"
									  style="color: white;"> <?php echo $type_name ?></span></h2>
							<h2><?php echo $leader; ?><strong><?php echo $last_send_time . $last_send_count; ?></strong>
							</h2>
							<input type="text"
								   class="form-control"
								   name="mailing-title"
								   placeholder="Titel på utskicket"
								   value="<?php echo $m[ 'title' ]; ?>"/>

						</div>
						<div class="col-sm-">
						</div>
						<div class="col-sm-2">
							<ul class="nav navbar-right panel_toolbox">
								<li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a>
								</li>
							</ul>
						</div>
						<div class="clearfix"></div>
					</div>
					<div class="x_content">


						<?php if ( $m[ 'type' ] === 'email' ): ?>
							<input class="form-control" name="mailing-name" placeholder="E-postens rubrik"
								   value="<?php echo $m[ 'name' ]; ?>"/>
							<div class="col-sm-9 col-xs-12">
								<div id="alerts"></div>
								<div class="btn-toolbar editor" data-role="editor-toolbar"
									 data-target="#<?php echo $m[ 'index' ]; ?>"
									 id="toolbar-<?php echo $m[ 'index' ] ?>">
									<div class="btn-group">
										<a class="btn dropdown-toggle" data-toggle="dropdown" title="Font"><i
													class="fa fa-font"></i><b class="caret"></b></a>
										<ul class="dropdown-menu">
										</ul>
									</div>

									<div class="btn-group">
										<a class="btn dropdown-toggle" data-toggle="dropdown" title="Font Size"><i
													class="fa fa-text-height"></i>&nbsp;<b class="caret"></b></a>
										<ul class="dropdown-menu">
											<li>
												<a data-edit="fontSize 5">
													<p style="font-size:17px">Huge</p>
												</a>
											</li>
											<li>
												<a data-edit="fontSize 3">
													<p style="font-size:14px">Normal</p>
												</a>
											</li>
											<li>
												<a data-edit="fontSize 1">
													<p style="font-size:11px">Small</p>
												</a>
											</li>
										</ul>
									</div>

									<div class="btn-group">
										<a class="btn" data-edit="bold" title="Bold (Ctrl/Cmd+B)"><i
													class="fa fa-bold"></i></a>
										<a class="btn" data-edit="italic" title="Italic (Ctrl/Cmd+I)"><i
													class="fa fa-italic"></i></a>
										<a class="btn" data-edit="strikethrough" title="Strikethrough"><i
													class="fa fa-strikethrough"></i></a>
										<a class="btn" data-edit="underline" title="Underline (Ctrl/Cmd+U)"><i
													class="fa fa-underline"></i></a>
									</div>

									<div class="btn-group">
										<a class="btn" data-edit="insertunorderedlist" title="Bullet list"><i
													class="fa fa-list-ul"></i></a>
										<a class="btn" data-edit="insertorderedlist" title="Number list"><i
													class="fa fa-list-ol"></i></a>
										<a class="btn" data-edit="outdent" title="Reduce indent (Shift+Tab)"><i
													class="fa fa-dedent"></i></a>
										<a class="btn" data-edit="indent" title="Indent (Tab)"><i
													class="fa fa-indent"></i></a>
									</div>

									<div class="btn-group">
										<a class="btn" data-edit="justifyleft" title="Align Left (Ctrl/Cmd+L)"><i
													class="fa fa-align-left"></i></a>
										<a class="btn" data-edit="justifycenter" title="Center (Ctrl/Cmd+E)"><i
													class="fa fa-align-center"></i></a>
										<a class="btn" data-edit="justifyright" title="Align Right (Ctrl/Cmd+R)"><i
													class="fa fa-align-right"></i></a>
										<a class="btn" data-edit="justifyfull" title="Justify (Ctrl/Cmd+J)"><i
													class="fa fa-align-justify"></i></a>
									</div>

									<div class="btn-group">
										<a class="btn dropdown-toggle" data-toggle="dropdown" title="Hyperlink"><i
													class="fa fa-link"></i></a>
										<div class="dropdown-menu input-append">
											<input class="span2" placeholder="URL" type="text" data-edit="createLink"/>
											<button class="btn" type="button">Add</button>
										</div>
										<a class="btn" data-edit="unlink" title="Remove Hyperlink"><i
													class="fa fa-cut"></i></a>
									</div>

									<div class="btn-group">
										<a class="btn" title="Insert picture (or just drag & drop)" id="pictureBtn"><i
													class="fa fa-picture-o"></i></a>
										<input type="file" data-role="magic-overlay" data-target="#pictureBtn"
											   data-edit="insertImage"/>
									</div>

									<div class="btn-group">
										<a class="btn" data-edit="undo" title="Undo (Ctrl/Cmd+Z)"><i
													class="fa fa-undo"></i></a>
										<a class="btn" data-edit="redo" title="Redo (Ctrl/Cmd+Y)"><i
													class="fa fa-repeat"></i></a>
									</div>
									<div class="btn-group">
										<a class="btn" data-edit="html" title="HTML-edit"><i class="fa fa-code"></i></a>
									</div>
								</div>
								<div id="<?php echo opt( $t, $m[ 'index' ] ) . '_CONTENT'; ?>"
									 class="editor-wrapper"><?php echo stripslashes( get_option( opt( $t, $m[ 'index' ] ) ) ); ?></div>
								<textarea name="mailing-content"
										  data-id="<?php echo opt( $t, $m[ 'index' ] ) . '_CONTENT' ?>"
										  class="form-control wysiwyg"
										  style="display:none;"><?php echo stripslashes( get_option( opt( $t, $m[ 'index' ] ) ) ); ?></textarea>
								<h3>Anteckningar om Utskick.</h3>
								<textarea rows="10" name="mailing-notes"
										  class="form-control"><?php echo get_option( opt( $t, $m[ 'index' ] . '_NOTES' ) ); ?></textarea>
							</div>


						<?php else: ?>
							<div class="col-sm-9 col-xs-12">
								<textarea rows="10"
										  name="mailing-content"
										  class="form-control sms"><?php echo get_option( opt( $t, $m[ 'index' ] ) ); ?></textarea>

								<h3>Anteckningar om Utskick.</h3>
								<textarea rows="10"
										  name="mailing-notes"
										  class="form-control"><?php echo get_option( opt( $t, $m[ 'index' ] . '_NOTES' ) ); ?></textarea>
							</div>
						<?php endif; ?>


						<div class="col-sm-3 col-xs 12">
							<h3>Taggar att använda</h3>
							<ul>
								<?php
								$taglist = $participant_tags;
								if ( $m[ 'leader' ] ) {
									$taglist = $leader_tags;
								}
								?>
								<?php foreach ( $taglist as $tag ) : ?>
									<li><?php echo $tag; ?></li>
								<?php endforeach; ?>
							</ul>
						</div>

						<div class="form-group">
							<div class="col-md-12 col-sm-12 col-xs-12">
								<input type="hidden" name="action" value="save-mailing"/>
								<input type="hidden" name="mailing-option" value="<?php echo $m[ 'index' ]; ?>"/>
								<input type="hidden" name="mailing-type" value="<?php echo $m[ 'type' ]; ?>"/>
								<button type="submit" name="cancel" class="btn btn-primary" data-form="mailing-form">
									Avbryt
								</button>
								<button type="submit" name="save-mailing" class="btn btn-success"
										data-form="mailing-form">Spara
								</button>
								<button type="submit" name="disable-mailing" class="btn btn-danger"
										data-form="mailing-form">Radera
								</button>
							</div>
						</div>


					</div>
				</div>
			</form>


		<?php endforeach; ?>
		<!-- END of Mailings -->

		<!-- Add mailing buttons -->
		<form method="POST">
			<input type="hidden" name="add-mailing"/>
			<button class="btn btn-primary" name="add-email">Lägg till E-post</button>
			<button class="btn btn-default" name="add-sms">Lägg till SMS</button>
			<button class="btn btn-warning" name="add-leader-email">Lägg till E-post för <strong>Ledare</strong>
			</button>
			<button class="btn btn-info" name="add-leader-sms">Lägg till SMS för <strong>Ledare</strong></button>
		</form>
		<!-- END of Add mailing buttons -->
	</div>


</div>
<!-- END of page content -->

<?php include 'footer-scripts.php'; ?>

<!-- bootstrap-wysiwyg -->
<script>
	$(document).ready(function () {

		// "All" check buttons.
		$('.u-all-button').on('click', function (e) {
			var targetClass = '.' + $(this).data('target');

			if ($(this).prop('checked')) {
				$(targetClass).prop('checked', false);
			}
		});

		// "All" check button groups.
		$('.u-all-group').on('click', function (e) {
			var targetID = '#' + $(this).data('target-id');

			if ($(this).prop('checked')) {
				$(targetID).prop('checked', false);
			}
		});


		$('button[data-form="send-form"]').on('click', function (e) {
			e.preventDefault();

			// Make sure all form fields are valid.
			if (!validateForm()) {
				return;
			}

			var endpoint = '<?php echo home_url( 'wp-json/hwcrm/v2/mailing/batch' ); ?>';

			var showTotal = false;

			$('#u-type').val($('#u-content').find(':selected').data('type'));

			if ($('#u-content').find(':selected').data('leader') === 'yes') {
				endpoint = '<?php echo home_url( 'wp-json/hwcrm/v2/mailing/leader/batch' ); ?>';
				showTotal = true;
			}

			sendBatch( endpoint, 0, showTotal );
		});

		function sendBatch( endpoint, batchNumber, showTotal = true) {

			if ( batchNumber === 0 ) {
				$('#mailing-count').css('display', 'block');
				$('#failed-mailings').fadeOut();
				$('#failed-mailings').html('');
			} else {
				//return;
			}

			var batchSize = 50;
			var formData  = getFormObject( $('#u-send-form').serializeArray() );
			Object.assign(formData, {
				batch_number: batchNumber,
				batch_size: batchSize
			});



			$.ajax({
				url: endpoint,
				method: 'POST',
				data: formData,
				success: function (response) {

					if (batchNumber === 0) {
						if ( showTotal ) {
							$('#total-mailings').html(response.total_recipients);
						}
					}

					var prevSent = parseInt($('#sent-mailings').html());
					var total = parseInt($('#total-mailings').html());
					var newSentCount = prevSent + parseInt( response.sent );

					$('#sent-mailings').html( newSentCount );

					if (response.failed.length > 0) {
						$('#failed-mailings').css('display', 'block');

						for ( var i = 0; i < response.failed.length; i++ ) {
							var user = response.failed[i];
							for ( var j = 0; j < user.length; j++ ) {
								$('#failed-mailings').append('<p>'+user[j]+'</p>');
							}
						}
					}

					if ( response.status !== 'finished' ) {
						sendBatch(endpoint, batchNumber + 1);

					} else {
						$('button[data-form="send-form"]').html('Klar!');

						window.setTimeout(function() {
							$('#mailing-count').fadeOut();
							$('button[data-form="send-form"]').html('Skicka');
							$('#sent-mailings').html('0');
							$('#total-mailings').html('?');
						}, 1000);

						// Save the last sent status.
						$.ajax({
							method: 'POST',
							url: '<?php echo home_url('wp-json/hwcrm/v2/mailing/last-sent'); ?>',
							data: {
								sent: parseInt($('#sent-mailings').html()),
								option: $('#u-content').val(),
								event_id: $('[name="event_id"]').val()
							}
						});
					}

				},
				error: function (response) {
					console.log('error');
					console.log(response);
				}

			});
			return true;
		}

		function getFormObject( formArray ) {
			var returnObject = {};

			for (var i = 0; i < formArray.length; i++ ) {
				returnObject[formArray[i].name] = formArray[i].value;
			}

			return returnObject;
		}
		$('#u-content').on('change', function () {

			if ($(this).find(':selected').data('leader') === 'yes') {
				$('#u-filters').hide();
			} else {
				$('#u-filters').show();
			}
		});

		function validateForm() {
			var formValid = true;
			// Validation of Content.
			if ($('#u-content').val() === '0') {
				new PNotify({
					type: 'error',
					title: 'Utskick',
					text: 'Välj vilket utskick du vill skicka',
					styling: 'bootstrap3',
				});
				formValid = false;
			}

			if ($('#u-content').find(':selected').data('leader') === 'yes') {
				return formValid;
			}

			// Validation of Recipient.
			if (!$('#u-rec-parent').prop('checked') && !$('#u-rec-child').prop('checked')) {
				new PNotify({
					type: 'error',
					title: 'Mottagare',
					text: 'Du måste välja minst en mottagare.',
					styling: 'bootstrap3',
				});
				formValid = false;
			}

			// Validation of Ticket.
			var chosenTicket = false;
			$('.u-filter-ticket').children().each(function (e) {
				if ($(this).prop('checked')) {
					chosenTicket = true;
				}
			});

			if (!chosenTicket) {
				new PNotify({
					type: 'error',
					title: 'Filter - Biljett',
					text: 'Utskicket måste ha minst en biljetttyp.',
					styling: 'bootstrap3'
				});
				formValid = false;
			}

			// Validation of Train.
			var chosenTrain = false;
			$('.u-filter-train').children().each(function (e) {
				if ($(this).prop('checked')) {
					console.log('checked');
					chosenTrain = true;
				}
			});
			console.log('nah');

			if (!chosenTrain) {
				new PNotify({
					type: 'error',
					title: 'Filter - Tåg',
					text: 'Du måste markera minst ett tågfilter. Om du inte vill filterera på tåg, markera <strong>Alla</strong>',
					styling: 'bootstrap3'
				});
				formValid = false
			}

			// Validation of Track.
			var chosenTrack = false;
			$('.u-filter-track').children().each(function (e) {
				if ($(this).prop('checked')) {
					chosenTrack = true;
				}
			});

			if (!chosenTrack) {
				new PNotify({
					type: 'error',
					title: 'Filter - Spår',
					text: 'Du måste markera minst ett spårfilter. Om du inte vill filterera på spår, markera <strong>Alla</strong>',
					styling: 'bootstrap3'
				});
				formValid = false;
			}

			return formValid;
		}

		$('button[name="save-mailing"]').click(function (e) {
			$('textarea.wysiwyg').each(function () {
				var textId = $(this).attr('data-id');
				console.log(textId);
				console.log($('#' + textId).cleanHtml());
				$(this).val($('#' + textId).cleanHtml());
			});

			return true;
		});


	});


	// bootstrap wysiwyg
	$(document).ready(function () {

		function initToolbarBootstrapBindings() {
			var fonts = ['Serif', 'Sans', 'Arial', 'Arial Black', 'Courier',
					'Courier New', 'Comic Sans MS', 'Helvetica', 'Impact', 'Lucida Grande', 'Lucida Sans', 'Tahoma', 'Times',
					'Times New Roman', 'Verdana'
				],
				fontTarget = $('[title=Font]').siblings('.dropdown-menu');
			$.each(fonts, function (idx, fontName) {
				fontTarget.append($('<li><a data-edit="fontName ' + fontName + '" style="font-family:\'' + fontName + '\'">' + fontName + '</a></li>'));
			});
			$('a[title]').tooltip({
				container: 'body'
			});
			$('.dropdown-menu input').click(function () {
				return false;
			})
				.change(function () {
					$(this).parent('.dropdown-menu').siblings('.dropdown-toggle').dropdown('toggle');
				})
				.keydown('esc', function () {
					this.value = '';
					$(this).change();
				});

			$('[data-role=magic-overlay]').each(function () {
				var overlay = $(this),
					target = $(overlay.data('target'));
				overlay.css('opacity', 0).css('position', 'absolute').offset(target.offset()).width(target.outerWidth()).height(target.outerHeight());
			});

			if ("onwebkitspeechchange" in document.createElement("input")) {
				var editorOffset = $('#editor').offset();

				$('.voiceBtn').css('position', 'absolute').offset({
					top: editorOffset.top,
					left: editorOffset.left + $('#editor').innerWidth() - 35
				});
			} else {
				$('.voiceBtn').hide();
			}
		}

		function showErrorAlert(reason, detail) {
			var msg = '';
			if (reason === 'unsupported-file-type') {
				msg = "Unsupported format " + detail;
			} else {
				console.log("error uploading file", reason, detail);
			}
			$('<div class="alert"> <button type="button" class="close" data-dismiss="alert">&times;</button>' +
				'<strong>File upload error</strong> ' + msg + ' </div>').prependTo('#alerts');
		}

		initToolbarBootstrapBindings();

		$('.editor-wrapper').each(function () {
			$(this).wysiwyg({
				fileUploadError: showErrorAlert,
				toolbarSelector: '#toolbar-' + $(this).attr('id'),
				toolbar: {
					"html": true
				}
			});
		});

		window.prettyPrint;
		prettyPrint();


	});
	$(function () {
		$('.collapsed').css('height', 'auto');
		$('.collapsed').find('.x_content').css('display', 'none');
		$('.collapsed').find('.x_title').find('i').toggleClass('fa-chevron-up fa-chevron-down');
	});
</script>
<!-- /bootstrap-wysiwyg -->
<?php include 'footer.php'; ?>
