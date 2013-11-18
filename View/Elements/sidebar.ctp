<?php
	require_once(APPLIBS.'set_shared_vars.php');
	$sidebar_vars = getSharedVariables($this->params);

	/* Provides the following:
	 * 		$states
	 * 		$state_abbreviations
	 * 		$selected_state
	 * 		$selected_county
	 * 		$selected_tab
	 * 		$selected_topic
	 * 		$sidebar_mode
	 * 		$topics
	 * 		$profiles_link 		*/
	$logged_in = $this->Session->check('Auth.User.id');
?>

<div id="sidebar">
	<!-- The following <a><img /></a> needs to be on one line or Internet Explorer will render it with trailing space -->
	<a href="/" title="Click to return to the home page" id="sidebar_topper"><img src="/img/navigationtopper.png" /></a>
	<div class="inner">
		<?php if ($logged_in): ?>
			<?php if ($sidebar_mode == 'county'): ?>
				<?php echo $this->element('sidebar_county', compact('selected_state', 'selected_county', 'selected_tab', 'selected_topic', 'topics', 'states', 'state_abbreviations', 'counties_full_names', 'counties_simplified')); ?>
			<?php elseif ($sidebar_mode == 'home'): ?>
				<?php echo $this->element('sidebar_home', compact('states', 'state_abbreviations', 'counties_full_names', 'counties_simplified')); ?>
			<?php elseif ($sidebar_mode == 'tif'): ?>
				<?php echo $this->element('sidebar_tif', compact('selected_county', 'states', 'state_abbreviations', 'naics_industries', 'counties')); ?>
			<?php endif; ?>
			<hr />
		<?php endif; ?>
		<div class="other_links">
			<a href="/">Home</a>
			<?php if ($logged_in): ?>
				<?php echo $this->Html->link(
					'Brownfield Grants Awarded in Indiana', 
					array(
						'controller' => 'pages', 
						'action' => 'grants_awarded'
					)
				); ?>
				<?php echo $this->Html->link(
					'TIF-in-a-Box', 
					array(
						'controller' => 'calculators', 
						'action' => 'tif'
					)
				); ?>
				<?php echo $this->Html->link(
					'Additional Resources', 
					array(
						'controller' => 'pages', 
						'action' => 'resources'
					)
				); ?>
				<a href="http://profiles.cberdata.org/">CBER County Profiles</a>
				<?php echo $this->Html->link(
					'Log Out', 
					array(
						'controller' => 'users', 
						'action' => 'logout'
					)
				); ?>
			<?php else: ?>
				<?php echo $this->Html->link(
					'Log In', 
					array(
						'controller' => 'users', 
						'action' => 'login'
					), 
					array(
						'id' => 'login_link_sidebar'
					)
				); ?>
				<?php if (! (isset($this->params['action']) && $this->params['action'] == 'login')): ?>
					<?php
						$this->Js->get('#login_link_sidebar');
						$this->Js->event('click', "showSidebarLogin()", array('stop' => true));
					?>
					<div id="login_sidebar" style="display: none;">
						<div>
							<?php echo $this->element('login'); ?>
						</div>
					</div>
				<?php endif; ?>
				<?php echo $this->Html->link(
					'Register Account', 
					array(
						'controller' => 'users', 
						'action' => 'register'
					)
				); ?>
			<?php endif; ?>
			<?php echo $this->Html->link(
				'Testimonials', 
				array(
					'controller' => 'pages', 
					'action' => 'testimonials'
				)
			); ?>
			<?php echo $this->Html->link(
				'Contact Us', 
				array(
					'controller' => 'pages', 
					'action' => 'contact'
				)
			); ?>
		</div>
	</div>
	
	<div class="inner awards">
		<h2>
			Awards
		</h2>
		<ul>
			<li>
				<strong>IEDC Honorable Mention - 2011</strong>
				<br />
				Special Purpose Website
				<br />
				<a class="awarder" href="http://www.iedconline.org/">International Economic Development Council</a>
			</li>
			<li>
				<strong>UEDA Summit Award<br />of Excellence Finalist - 2011</strong>
				<br />
				Excellence in Research<br />and Analysis
				<br />
				<a class="awarder" href="http://www.iedconline.org/">University Economic Development Association</a>
			</li>
		</ul>
	</div>
</div>