<div class="source">
	<?php if (isset($sources) && ! empty($sources)): ?>
		<ul>
			<?php foreach ($sources as $source): ?>
				<li><?php echo $text->autoLink(nl2br($source)); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php else: ?>
		(There was an error retrieving the sources for this data set.)
	<?php endif; ?>
</div>