

<script name="model">

	/*jshint esversion: 6 */
	/*global $:false */
	/*global console:false */

  class Model {
	  static getToken(onSuccess, onError) {
		$.ajax({
			  url: '<?php echo home_url('wp-json/hwcrm/v2/trello/token'); ?>',
			  success: data => {
				  if (data.token && data.token !== undefined) {
					  onSuccess(data.token);
				  } else {
				  	onError();
					}
			  },
			  error: onError
		});
	  }

	  static sendConfirmationMessage(childName, checkedIn, mobiles, onSuccess, onError) {
		  $.ajax({
			  method: 'POST',
			  url: '<?php echo home_url('wp-json/hwcrm/v2/sms'); ?>',
			  data: {
				  recipients: mobiles,
				  name: childName,
				  checked_in: checkedIn,
				  sender: 'Hello World!',
			  }
		  }).done(d => {
			  let returnData = d;
			  if (returnData['error'] === undefined) {
				  onSuccess(returnData);
			  } else {
				  onError(returnData);
			  }
		  }).fail(d => {
			  onError(d);
		  });
	  }
  }


</script>