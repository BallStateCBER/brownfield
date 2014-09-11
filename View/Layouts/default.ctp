<?php 
	$this->extend('DataCenter.default');
	$this->assign('sidebar', $this->element('sidebar'));
	$this->Html->script('script', array('inline' => false));
	echo $this->element('flash_messages', array(), array('plugin' => 'DataCenter'));
?>
<div id="content">
	<?php echo $this->fetch('content'); ?>
</div>