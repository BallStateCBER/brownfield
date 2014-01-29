<h1 class="page_title">
	Brownfield Grants Awarded in Indiana
</h1>

<?php echo $this->element('reports/svgchart', array(
	'topic' => 'grants_awarded', 
	'state' => 'in', 
	'county' => 'arbitrary county', 
	'availability' => 0
)); ?>

<table class="grants_awarded">
	<?php foreach ($grants as $year => $year_set): ?>
		<tr>
			<td rowspan="<?php echo count($year_set); ?>" class="year">
				<?php echo $year; ?>
			</td>
			<?php $first = true; ?>
			<?php foreach ($year_set as $recipient => $grants): ?>
				<?php if (! $first): ?>
					</tr><tr>
				<?php endif; ?>
				<td class="recipient">
					<?php echo $recipient; ?>
				</td>
				<td class="grants">
					<ul>
						<?php foreach ($grants as $grant): ?>
							<li>
								<a href="<?php echo $grant['url']; ?>">
									<?php echo $grant['type']; ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</td>
				<?php $first = false; ?>
			<?php endforeach; ?>
		</tr>
	<?php endforeach; ?>
</table>

<p>
	Source: <a href="http://cfpub.epa.gov/bf_factsheets/basic/index.cfm">http://cfpub.epa.gov/bf_factsheets/basic/index.cfm</a>
</p>