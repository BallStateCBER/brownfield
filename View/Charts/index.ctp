<?php
if (isset($_GET['debug'])) {
	var_dump($chart->getQuery());
	echo $chart->validate();
	echo $chart->toHtml();
} else {
	echo $chart;
}