<?php
	$this->extend('DataCenter.default');
	$this->assign('sidebar', $this->element('sidebar'));
	$this->Html->script('script', array('inline' => false));
	$this->start('flash_messages');
	    echo $this->element('flash_messages', array(), array('plugin' => 'DataCenter'));
	$this->end();
?>

<?php $this->start('subsite_title'); ?>
	<h1 id="subsite_title" class="max_width">
		<a href="/">
			<img src="/img/Brownfield.jpg" alt="Brownfield Grant Writers' Toolbox" />
		</a>
	</h1>
<?php $this->end(); ?>

<div id="content">
	<?php echo $this->fetch('content'); ?>
</div>