<?php /*
	Available to this view: 
		county_id
		state_id
		state_name_simplified
		county_name_simplified
		selected_topic
		description
		sources
		title_for_layout
		topic_full_name
*/ ?>
<h1 class="page_title"><?php echo $topic_full_name; ?></h1>
<div class="topic">
    <?php
        $topic = $selected_topic;
        $description = '<p class="description">'.$this->Text->autoLink(nl2br($description)).'</p>';

        $chart_availability = $this->requestAction("/reports/getStatus/chart/$topic/$state_id/$county_id");
        $chart = $this->element('reports/chart', array(
            'topic' => $selected_topic,
            'state' => $state_name_simplified,
            'county' => $county_name_simplified,
            'availability' => $chart_availability
        ));

        $table_availability = $this->requestAction("/reports/getStatus/table/$topic/$state_id/$county_id");
        $table = $this->element('reports/table', array(
            'topic' => $selected_topic,
            'state' => $state_name_simplified,
            'county' => $county_name_simplified,
            'availability' => $table_availability
        ));

        $csv_availability = $this->requestAction("/reports/getStatus/csv/$topic/$state_id/$county_id");
        $csv_link = "/csv/$selected_topic/$state_name_simplified/$county_name_simplified";

        $source_availability = $this->requestAction("/reports/getStatus/source/$topic/$state_id/$county_id");
        $source_element = $this->element('reports/source', array(
            'topic' => $selected_topic,
            'state' => $state_name_simplified,
            'county' => $county_name_simplified,
            'availability' => $source_availability,
            'sources' => $this->requestAction("/reports/switchboard/source/$topic/$state_id/$county_id")
        ));
    ?>
    <?php if ($chart_availability == 1): // Chart not supported for this topic ?>
        <?php echo $table; ?>
        <?php echo $description; ?>
    <?php else: ?>
        <?php echo $chart ?>
        <?php echo $description; ?>
        <fieldset class="collapsible collapsed">
            <legend>Data Table</legend>
            <?php echo $table; ?>
        </fieldset>
    <?php endif; ?>
    <?php if ($csv_availability == 0): ?>
        <fieldset class="collapsible collapsed">
            <legend>Download</legend>
            <div>
                <a href="<?php echo $csv_link; ?>">
                    Download CSV spreadsheet
                </a>
            </div>
        </fieldset>
    <?php endif; ?>
    <fieldset class="collapsible collapsed">
        <legend>Source</legend>
        <?php echo $source_element; ?>
    </fieldset>
</div>

<script type="text/javascript">setupCollapsibleFieldsets();</script>