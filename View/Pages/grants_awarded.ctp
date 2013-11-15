<h1 class="page_title">
	Brownfield Grants Awarded in Indiana
</h1>

<img src="http://chart.apis.google.com/chart?cht=lc&chs=500x200&chg=10%2C10%2C1%2C5&chtt=Brownfield+Grants+Awarded+in+Indiana+%281995+-+2011%29&chd=t:2,1,2,1,2,1,2,1,4,5,2,5,10,6,4,7,10&chds=0.1%2C10.9&chxt=x%2Cy&chxr=0%2C1995%2C2011%7C1%2C0.1%2C10.9&chxs=0N%2Af0y%2A%2C666666%7C1N%2Af0sy%2A%2C666666" />
<?php /* Data for new chart
Year	#
1995	2
1996	1
1997	2
1998	1
1999	2
2000	1
2001	2
2002	1
2003	4
2004	5
2005	2
2006	5
2007	10
2008	6
2009	4
2010	7
2011	10
2,1,2,1,2,1,2,1,4,5,2,5,10,6,4,7,10
*/ ?>

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