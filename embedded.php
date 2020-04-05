<?php isset($this) or die(); ?>
<iframe id="paymentgatewayframe" name="paymentgatewayframe" frameBorder="0" seamless='seamless' style="width:699px; height:1073px;margin: 0 auto;display:block;"></iframe>
<form id="paymentgatewaymoduleform" action="<?=$this->gateway_url?>" method="post" target="paymentgatewayframe">
<?php
	foreach ($req as $key => $value) {
		echo '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
	}
?>
</form>
<script>
	// detects if jquery is loaded and adjusts the form for mobile devices
	document.body.addEventListener('load', function() {
		if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ) {
			var frame = document.querySelector('#paymentgatewayframe');
			frame.style.height = '1300px';
			frame.style.width = '50%';
		}
	});
	document.getElementById('paymentgatewaymoduleform').submit();
</script>