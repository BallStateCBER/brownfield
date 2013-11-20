<div id="sidebar">
	<h2>
		<a href="/">
			Home
		</a>
	</h2>
	
	<div class="inner">
		<?php 
			if ($sidebar_mode == 'county') {
				echo $this->element('sidebar_county', compact(
					'selected_state', 
					'selected_county', 
					'selected_tab', 
					'selected_topic', 
					'topics', 
					'states', 
					'state_abbreviations', 
					'counties_full_names', 
					'counties_simplified'
				));
			} elseif ($sidebar_mode == 'home') {
				echo $this->element('sidebar_home', compact(
					'states', 
					'state_abbreviations', 
					'counties_full_names', 
					'counties_simplified'
				));
			} elseif ($sidebar_mode == 'tif') {
				echo $this->element('sidebar_tif', compact(
					'selected_county', 
					'states', 
					'state_abbreviations', 
					'naics_industries', 
					'counties'
				));
			}
		?>
		
		<hr />
		
		<ul class="other_links">				
			<li>
				<?php echo $this->Html->link(
					'Brownfield Grants Awarded in Indiana', 
					array(
						'controller' => 'pages', 
						'action' => 'grants_awarded'
					)
				); ?>
			</li>
			<li>
				<?php echo $this->Html->link(
					'TIF-in-a-Box', 
					array(
						'controller' => 'calculators', 
						'action' => 'tif'
					)
				); ?>
			</li>
			<li>
				<?php echo $this->Html->link(
					'Additional Resources', 
					array(
						'controller' => 'pages', 
						'action' => 'resources'
					)
				); ?>
			</li>
			<li>
				<a href="http://profiles.cberdata.org/">
					CBER County Profiles
				</a>
			</li>
			<li>
				<?php echo $this->Html->link(
					'Testimonials', 
					array(
						'controller' => 'pages', 
						'action' => 'testimonials'
					)
				); ?>
			</li>
			<li>
				<?php echo $this->Html->link(
					'Contact Us', 
					array(
						'controller' => 'pages', 
						'action' => 'contact'
					)
				); ?>
			</li>
		</ul>
	</div>
	
	<div class="inner awards">
		<h2>
			Awards
		</h2>
		<ul>
			<li>
				<strong>
					IEDC Honorable Mention - 2011
				</strong>
				<br />
				Special Purpose Website
				<br />
				<a class="awarder" href="http://www.iedconline.org/">
					International Economic Development Council
				</a>
			</li>
			<li>
				<strong>
					UEDA Summit Award
					<br />
					of Excellence Finalist - 2011
				</strong>
				<br />
				Excellence in Research<br />and Analysis
				<br />
				<a class="awarder" href="http://www.iedconline.org/">
					University Economic Development Association
				</a>
			</li>
		</ul>
	</div>
</div>