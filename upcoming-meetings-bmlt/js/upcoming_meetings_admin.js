function getUpcomingMeetingsValueSelected() {
	var x = document.upcoming_meetings_options.service_body_dropdown.selectedIndex;
	var res = document.upcoming_meetings_options.service_body_dropdown.options[x].value.split(",");
	document.getElementById("txtSelectedValues1").innerHTML = '<b>Service Body ID:</b> <span class="bmlt_sb">' + res[1] + '</span>';
	document.getElementById("txtSelectedValues2").innerHTML = '<b>Service Body Parent:</b> <span class="bmlt_sb">' + res[3] + '</span>, <b>Service Body Parent ID:</b> <span class="bmlt_sb">' + res[2] + '</span>';
};

jQuery(document).ready(function($) {
	$("#upcoming_meetings_accordion").accordion({
		heightStyle: "content",
		active: false,
		collapsible: true
	});
	$(".upcoming_meetings_service_body_select").chosen({
		inherit_select_classes: true,
		width: "50%"
	});
});
