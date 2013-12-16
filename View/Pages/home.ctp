<?php
	$total_topics = 0;
	foreach ($topics as $c => $t) {
		$total_topics += count($t);
	}
?>

<div id="site_intro">
	<h1>Data</h1>
	<div class="section">
		<p>
			Browse graphs, data tables, and downloadable spreadsheets of up-to-date, county-level data.
			All data includes links to the original source to assist you in further research. 
		</p>
		<div class="previews">
			<a href="/img/preview/preview4.png" rel="shadowbox[Previews]"><img src="/img/preview/preview4.png" /></a>
			<a href="/img/preview/preview1.png" rel="shadowbox[Previews]"><img src="/img/preview/preview1.png" /></a>
			<a href="/img/preview/preview3.png" rel="shadowbox[Previews]"><img src="/img/preview/preview3.png" /></a>
		</div>
	</div>

	<h1>Topics</h1>
	<div>
		<div class="section">
			<p>
				Each of our <?php echo $total_topics; ?> topics, covering the <strong>demographics</strong>, 
				<strong>economics</strong>, and <strong>health</strong> of your county, include explanations
				of the topic's relevance to brownfield sites and brownfield cleanup efforts.
			</p>
			<div id="preview_topics_teaser">
				<div>
					<ul>
						<li>Cancer Death and Incidence Rates</li>
						<li>Death Rate by Cause</li>
						<li>Age Breakdown of Disabled Citizens</li>
						<li>Percent of Citizens in Poverty</li>
						<li>Unemployment Rate</li>
						<li>Personal and Household Income</li>
						<li><a href="#" id="preview_all_topics">View all <?php echo $total_topics; ?>...</a></li>
					</ul>
				</div>
			</div>
			<div id="preview_topics_full" style="display: none;">
				<table>
					<?php foreach ($topics as $category => $category_topics): ?>
						<tr>
							<td>
								<img src="/img/<?php echo $category; ?>.png" />
							</td>
							<td>
								<ul>
									<?php foreach ($category_topics as $topic_key => $topic_title): ?>
										<li>
											<?php echo $topic_title; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		</div>
	</div>
	
	<h1>TIF-in-a-Box</h1>
	<div class="section">
		<p>
			Our <strong>TIF-in-a-Box</strong> feature presents information about Tax Increment Financing, 
			a tool used by local governments to allocate changes to local taxes caused by new business development 
			for a specific purpose. 
		</p>
		<p>
			Included in TIF-in-a-Box is the <strong>Economic Impact Calculator</strong>, a tool for estimating 
			the economic and fiscal effects of new business development.
		</p>
		<div class="previews">
			<a href="/img/preview/preview5.png" rel="shadowbox[Previews_Calc]"><img src="/img/preview/preview5.png" /></a>
			<a href="/img/preview/preview6.png" rel="shadowbox[Previews_Calc]"><img src="/img/preview/preview6.png" /></a>
		</div>
	</div>
	
	<p id="frontpage_feedback">
		<strong>We want your feedback</strong> to help us continue to improve this resource. If you have success stories,
		requests for improvements, or any other comments or questions about this website, please 
		email Project Manager Srikant Devaraj at <a href="mailto:sdevaraj@bsu.edu">sdevaraj@bsu.edu</a>.
	</p>
</div>

<?php 
	$this->Js->buffer("
		$('#preview_all_topics').click(function (event) {
			event.preventDefault();
			$('#preview_topics_teaser').slideUp(500, function() {
				$('#preview_topics_full').slideDown(500);
			});
		});
	");
	$this->Html->script('/shadowbox-3.0.3/shadowbox.js', array('inline' => false));
	$this->Html->css('/shadowbox-3.0.3/shadowbox.css', null, array('inline' => false));
	$this->Js->buffer('Shadowbox.init();');
?>