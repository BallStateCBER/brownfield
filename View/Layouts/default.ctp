<?php 
	$this->extend('DataCenter.default');
	$this->assign('sidebar', $this->element('sidebar'));
	$this->Html->script('script', array('inline' => false));
?>
<div id="content">
	<?php echo $this->fetch('content'); ?>
</div>