<?php
	$total_topics = 0;
	foreach ($topics as $c => $t) {
		$total_topics += count($t);
	}
?>

<div id="site_intro">
	<?php if (! $this->Session->check('Auth.User.id')): ?>
		<p>
			<?php echo $this->Html->link(
				'Sign up for a <strong>free account</strong>',
				array('controller' => 'users', 'action' => 'register'),
				array('escape' => false)
			); ?>
			or
			<?php echo $this->Html->link(
				'log in',
				array('controller' => 'users', 'action' => 'login'),
				array('id' => 'login_link_home')
			); ?>
			<?php
				$this->Js->get('#login_link_home');
				$this->Js->event('click', "showSidebarLogin()", array('stop' => true));
			?>
			to access the Brownfield Grant Writers' Tool.
		</p>
	<?php endif; ?>

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
	// Add to the JS buffer
	$this->Js->buffer("
		$('preview_all_topics').observe('click', function (event) {
			event.stop();
			Effect.SlideUp('preview_topics_teaser', {
				duration: 0.5,
				afterFinish: function() {
					Effect.SlideDown('preview_topics_full', {
						duration: 0.5
					});
				}
			});
		});
	");
?>