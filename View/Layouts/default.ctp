<?php 
	$this->extend('DataCenter.default');
	$this->assign('sidebar', $this->element('sidebar'));
?>
<div id="content">
	<?php echo $this->fetch('content'); ?>
</div>