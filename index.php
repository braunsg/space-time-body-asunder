<!--
#space-time-body-asunder
https://github.com/braunsg/space-time-body-asunder
Created by: Steven Braun
Last Update: 2016-06-27 Steven Braun

Open source code for "Space, Time, and Body Asunder: Mapping the Voices of the Hiroshima Archive"
http://www.stevengbraun.com/dev/hiroshima-archive/

This work is provided under the MIT License (MIT)

COPYRIGHT (C) 2016 Steven Braun

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:
	The above copyright notice and this permission notice shall be included in all
	copies or substantial portions of the Software.

A full copy of the license is included in LICENSE.md.

//////////////////////////////////////////////////////////////////////////////////////////
// About this file

Index page for interface, including JavaScript for creating main visualization

-->

<!DOCTYPE html>
<head>
	<meta charset="UTF-8">
	<title>Space, Time, and Body Asunder: Mapping the Voices of the Hiroshima Archive</title>

	<!-- Load stylesheet -->
	<link rel="stylesheet" type="text/css" href="inc/default-style.css">

	<!-- load libraries, functions -->
	<script src="inc/jquery-1.11.2.min.js"></script>
	<script src="inc/d3.v3.min.js"></script>
</head>
<body>
<?php

// Determine language option
if($_GET["lang"] === "jp") {
	$lang = "jp";
	include("inc/content-jp.htm");
} else {
	$lang = "en";
	include("inc/content-en.htm");
}

// Get frequencies for tokens of categories body, space, and time 
$special_tokens = array("body" => array(),
						"space" => array(),
						"time" => array(),
						"physic" => array());
						
// Get tokens to search for
$tokens = array();
$token_data = array();

// Load token data from file
$token_data_file = fopen("inc/token_data.csv","r");
while($row = fgetcsv($token_data_file)) {
	$token_id = $row[0];
	$token_glyph = $row[1];
	$token_class = $row[2];
	
	$tokens[$token_id] = $token_glyph;
	$token_data[$token_glyph] = array("token_glyph" => $token_glyph,
							   "token_id" => $token_id,
							   "token_class" => $token_class);
	
	if(array_key_exists($token_class,$special_tokens)) {
		if(count($special_tokens[$token_class]) < 10) {
			$special_tokens[$token_class][] = array("token_glyph" => $token_glyph,
													"token_id" => $token_id,
													"token_instance_ct" => $row[3]);
													
		}
	}
	
}

// Now iterate through all interviews and retrieve tokenizations for each
$filenames = array();
$filename_exceptions = array(".DS_Store");
foreach (new DirectoryIterator("inc/data/") as $fileInfo) {

		$filename = $fileInfo->getFilename();
		if($fileInfo->isDot() || in_array($filename,$filename_exceptions) || strpos($filename,"photograph") !== false || strpos($filename,"himeguri") !== false) continue;
		$filenames[] = $filename;

}	

// Sort $filenames array so that order will be replicated in browser
sort($filenames);

$token_sort_order = array("body" => 1,
						  "time" => 2,
						  "space" => 3,
						  "physic" => 4,
						  "person" => 5,
						  "place" => 6,
						  "event" => 7,
						  "idea" => 8,
						  "thing" => 9);

$max_count = 0;
$file_sequences = array();
foreach($filenames as $filename) {
	
	$file = file_get_contents("inc/data/" . $filename);
	$json = json_decode($file,true);

	$sequence = array();
	$sequence_counter = 0;
	foreach($json as $entry) {
		$query_string = $entry["query_string"];
		$entry_tokens = $entry["tokens"];
		foreach($entry_tokens as $i => $entry_token_data) {
		
			if(array_key_exists("surface",$entry_token_data)) {
				$token_glyph = $entry_token_data["surface"];
		
				if(array_key_exists($token_glyph,$token_data)) {
					$class = $token_data[$token_glyph]["token_class"];
				} else {
					$class = "none";
				}

				if($class === "none") {
					continue;
				} else {
					$sequence[] = array("token_glyph" => $token_glyph,
										"token_class" => $class,
										"unsorted_index" => $sequence_counter++,
										"sorted_index" => null);
				}				
				$previous_class = $class;
			}
		}
	
	}

	if(count($sequence) > $max_count) {
		$max_count = count($sequence);
	}

	usort($sequence, $f = function($a,$b) use ($token_sort_order) {
		if($token_sort_order[$a["token_class"]] > $token_sort_order[$b["token_class"]]) {
			return 1;
		} elseif($token_sort_order[$a["token_class"]] < $token_sort_order[$b["token_class"]]) {
			return -1;
		} else {
			return 0;
		}
	});
	
	foreach($sequence as $i => $sorted_entry) {
		$sequence[$i]["sorted_index"] = $i;
	}

	$file_sequences[$filename] = $sequence;
}

$num_entries = count($file_sequences);

// Convert data to JSON
$data = json_encode($file_sequences,JSON_UNESCAPED_UNICODE);
$special_tokens = json_encode($special_tokens,JSON_UNESCAPED_UNICODE);

?>

<script>

	// Define global variables
	var data = <?php echo $data; ?>;
	var lang = '<?php echo $lang; ?>';
	var special_tokens = <?php echo $special_tokens; ?>;
	var max_count = <?php echo $max_count; ?>;
	var num_entries = <?php echo $num_entries; ?>;
 	var dataset = "unsorted";
 	
	// Create visualizations when page is ready
	$(document).ready(function() {
		
		// Define functions
		d3.selection.prototype.moveToFront = function() {  
		  return this.each(function(){
			this.parentNode.appendChild(this);
		  });
		};
	
		function highlight_tokens(data,source) {					
			if(source === "node") {
				if(highlight == false) {
					svg.selectAll(".token_box").attr("opacity",0.1);
					svg.selectAll(".token_box").filter(function(k) {
						return k.token_glyph === data.token_glyph;
					}).attr("opacity",1);
					highlight = true;
				} else {
					svg.selectAll(".token_box").attr("opacity",1);
					highlight = false;
				}
			} else if(source === "key") {
				var token_class = data;
				svg.selectAll(".token_box").attr("opacity",0.1);
				svg.selectAll(".token_box").filter(function(k) {
					if(token_class === "default") {
						return k.token_class === "event" || k.token_class === "thing" || k.token_class === "idea";
					} else {
						return k.token_class === token_class;
					}
				}).attr("opacity",1);								
			}
		}	

		// Bind scroll functionality to arrows
		var scroller;
		
		$("#scroll_up").on("mouseover", function() {
			scroller = setInterval(function(){
				$("#main_vis").scrollTop($("#main_vis").scrollTop()-100);
			}, 100)  
		}).on("mouseout", function() {
			clearInterval(scroller);
		});

		$("#scroll_down").on("mouseover", function() {
			scroller = setInterval(function(){
				$("#main_vis").scrollTop($("#main_vis").scrollTop()+100);
			}, 100)  
		}).on("mouseout", function() {
			clearInterval(scroller);
		});
		
		// Generate SVG for navigation and key		
		function draw_key(which_vis) {
	
			if(which_vis === "main") {
				var vis_nav_div = "#main_vis_nav";
				var vis_nav_svg = "#main_nav_svg";
			} else if(which_vis === "treemap") {
				var vis_nav_div = "#treemap_vis_nav";
				var vis_nav_svg = "#treemap_nav_svg";		
			}
			var nav_svg_height = $(vis_nav_div).innerHeight();
			var nav_svg = d3.select(vis_nav_svg)
				.append("svg");

			var category_position = 15;
			var key_padding = 5;
			var key_dimension = 10;
			for(var category in categories) {
		
				if(category === "none") {
					continue;
				}
			
				var nav_g = nav_svg.append("g")
					.attr("height", nav_svg_height)
					.attr("transform","translate(" + category_position + ",0)");
				
				var text = nav_g.append("text")
					.attr("class","key_text")
					.attr("id","key_" + category)
					.attr("x", key_dimension + key_padding)
					.attr("y", nav_svg_height/2)
					.text(categories[category]["label-" + lang]);

				var bbox = text.node().getBBox();
			
				nav_g.attr("width",bbox.width + key_dimension + key_padding*2);

				nav_g.append("rect")
					.attr("width",key_dimension)
					.attr("height",key_dimension)
					.attr("x", 0)
					.attr("y",nav_svg_height/2 - 5)
					.attr("fill", categories[category]["color"]);
					
					
				if(which_vis === "main") {
					// Now bind filter event to key text -- only for main visualization
			
					text.on("click", function() {
						var filter_id = d3.select(this).attr("id").replace("key_","");
						highlight_tokens(filter_id,"key");
					}).on("mouseover", function() {
						d3.select(this).style("text-decoration","underline");
					}).on("mouseout", function() {
						d3.select(this).style("text-decoration","none");
					});
				}
						
				category_position += bbox.width + key_dimension + key_padding*4;
			
			}

			nav_svg.attr("width",category_position)
				.attr("height",nav_svg_height);
		
		}
	
		// Define dimensions of overall visualization
		var min_vis_height = $(window).innerHeight() - 500;		
		var width = $("#main_vis").innerWidth();	
		var margin = {top: 0, left: 0, right: 0, bottom: 0};

		// Define dimensions of word boxes
		var box_width = (width-margin.left-margin.right)/(num_entries-1);
		var box_height = box_width;

		// Height determined by total box distribution
		var height = box_height*max_count;
	
		// Now set height of div holding main visualization
		$("#main_vis").css("height",min_vis_height + $("#content_wrapper").innerHeight());

		// If user clicks on scroll header, expand vis window to full height
		var main_expanded = false;
		var initial_div_height = $("#main_vis").css("height");
		var initial_div_offset = $("#content_wrapper").css("top");
		
		$("#scroll_instructions").click(function() {
			if(main_expanded == false) {
				$("#main_vis").css({"height":$("#main_svg").height() + "px",
									"overflow-y":"hidden"});
				$("#content_wrapper").animate({top:$("#main_svg").offset().top + $("#main_svg").height() + "px"},4000);

				main_expanded = true;
			} else {
				$("#main_vis").css({"height":initial_div_height,
									"overflow-y":"scroll"});
				$("#content_wrapper").animate({top:initial_div_offset},1500);
				$("html,body").animate({scrollTop: initial_div_offset},1500);
				main_expanded = false;
			}
		});
		
		// Define token categories
		var categories = {"body":{"color":"#e41a1c",
								  "label-en":"Body",
								  "label-jp":"身体"},
						  "time":{"color":"#386cb0",
						  	 	  "label-en":"Time",
						  	 	  "label-jp":"時間"},
						  "physic":{"color":"#99cc99",
						  			"label-en":"Physical World",
						  			"label-jp":"物質世界"},
						  "person":{"color":"#984ea3",
						  			"label-en":"Person",
						  			"label-jp":"人"},
						  "space":{"color":"#fdc086",
						  		   "label-en":"Space",
						  		   "label-jp":"空間"},
						  "place":{"color":"#CECECE",
						  		   "label-en":"Place",
						  		   "label-jp":"場所"},
						  "none":{"color":"#FAFAFA",
						  	      "label-en":"none",
						  	      "label-jp":"none"},
						  "default":{"color":"#666666",
						  			 "label-en":"Events, things, ideas",
						  			 "label-jp":"出来事、物、その他"}};
	
		// Color special token section borders according to category
		$(".special_tk_descr").each(function(s) {
			var id = $(this).attr("id");
			var category = id.replace("special_tk_","");
			$(this).css("border-top","10px solid " + categories[category]["color"]);
		});
		
		// Generate main visualization	
		var svg = d3.select("#main_vis")
			.append("svg")
			.attr("id","main_svg")
			.attr("width",width)
			.attr("height",height)
			.attr("opacity",0);

		var y_scale = d3.scale.linear()
			.domain([0,max_count - 1])
			.range([margin.top,box_height * max_count]);

		var x_scale = d3.scale.linear()
			.domain([1, num_entries])
			.range([margin.left, width-margin.right-box_width]);
		
		// Generate tooltip and sort toggling for main visualization
		var tooltip = d3.select("#main_vis").append("div")	
			.attr("id","main_tooltip")
			.attr("class","tooltip")
			.style("opacity", 0);
								
		var toggle_sort_main = d3.select("#toggle_sort_main")
			.on("click", function() {
				svg.selectAll(".token_box")
					.attr("opacity",0);
					
				if(sort === false) {
					svg.selectAll(".token_box")
						.attr("y", function(k) {
							return y_scale(k.sorted_index);
						});
					sort = true;
				} else {
					svg.selectAll(".token_box")
						.attr("y", function(k) {
							return y_scale(k.unsorted_index);
						});
					sort = false;
		
				}							

				svg.selectAll(".token_box")
					.attr("opacity",1);			
			});
			
		// Generate boxes for main visualization
		var highlight = false;
		var sort = false;
		var filecounter = 0;
		for(var filename in data) {
			filecounter++;
			var classname = filename.replace(".json","");
			var entry = data[filename];
			var g = svg.append("g")
				.attr("id","g_" + classname)
				.attr("width",box_width)
				.attr("height",height)
				.attr("transform", function() {
					return "translate(" + x_scale(filecounter) + "," + margin.top + ")";
				});
			
			var boxes = g.selectAll(".box")
				.data(entry)
				.enter()
				.append("rect")
					.attr("class",classname + "_box")
					.classed("token_box",true)
					.attr("x",0)
					.attr("y", function(d) {
						return y_scale(d.unsorted_index);
					})
					.attr("width",box_width)
					.attr("height",box_height)
					.attr("stroke","#FF9C00")
					.attr("stroke-width",0)
					.attr("fill", function(d) {
						if(d.token_class in categories) {
							return categories[d.token_class]["color"];
						} else {
							return categories["default"]["color"];
						}
					})
					.on("mouseover", function(d) {
						d3.select(this).attr("stroke-width",2)
							.moveToFront();
						var position_top = Number(d3.select(this).attr("y"));
						var position_left = d3.transform(d3.select(this.parentNode).attr("transform")).translate[0] + box_width;
						tooltip.html(d.token_glyph);
						if(position_left < width*0.95) {
							tooltip.style("left", position_left + "px")	
								.style("right","auto")	
								.style("top", position_top + "px")
								.style("opacity",1);
						} else {
							tooltip.style("right", (width-position_left + box_width) + "px")	
								.style("left","auto")	
								.style("top", position_top + "px")
								.style("opacity",1);
						}
					}).on("mouseout", function() {
						d3.select(this).attr("stroke-width",0);
						tooltip.style("opacity",0);
					}).on("click", function(d) {
						highlight_tokens(d,"node");
					});
				
		} // end for(var filename in data)
		
		// Draw key SVG for main visualization
		draw_key("main");
		
		// Now generate treemap for highest-frequency terms overall
		d3.csv("inc/data/top-freq-tokens.csv", function(data) {

			var sorted = {name: "top_freq_tokens", children: []};
			var classes = {};
	
			var unsorted = {name: "top_freq_tokens", children: data};
	
			data.forEach(function(datum) {
				if(!(datum.token_class in classes)) {
					classes[datum.token_class] = [];
				}
				classes[datum.token_class].push(datum);
			});
			for(var token_class in classes) {
				sorted["children"].push({name: token_class, children: classes[token_class]});
			}
					
			var treemap_width = $("#treemap_vis").innerWidth();
			var treemap_height = $("#treemap_vis").innerHeight();
			var div = d3.select("#treemap_vis");
	   
			var treemap = d3.layout.treemap()
				.size([treemap_width,treemap_height])
				.sticky(false)
		
				.value(function(d) { return d.token_instance_ct; });
			var node;
			dataset = "unsorted";
			var transition_ct = 0;
	
			drawtree(dataset);
	
			function drawtree(tree_sort) {

				$("#treemap_vis").empty();
				if(tree_sort === "unsorted") {
					var tree_data = unsorted;
				} else if(tree_sort === "sorted") {
					var tree_data = sorted;
				}
				node = div.datum(tree_data).selectAll(".node")
					.data(treemap.nodes)
					.enter().append("div")
					.attr("class", "node")
					.style("opacity",0)
					.style("border-color", function(d) {
						if(d.token_class in categories) {
							return categories[d.token_class]["color"];
						} else {
							return categories["default"]["color"];
						}
					
					})
					.style("border-width","0px")
					.call(position)
					.style("background-color", function(d) {  
						var color_hex;    	
						if(!d.children) {
							if(d.token_class in categories) {
								color_hex = d3.rgb(categories[d.token_class]["color"]);
							} else {
								color_hex = d3.rgb(categories["default"]["color"]);
							}
							return "rgba(" + color_hex.r + "," + color_hex.g + "," + color_hex.b + ",0.5)";

						}

				}).style("font-size", function(d) {
					if(!d.children) {
						var token = d["token_label"].split("");
						var token_count = token.length;
						if(token_count == 1) {
							if($(this).innerHeight() > $(this).innerWidth()) {
								return $(this).innerWidth() * 0.8 + "px";
							} else {
								return $(this).innerHeight() * 0.8 + "px";
							}
						} else {
							if($(this).innerHeight() > $(this).innerWidth()) {
								return ($(this).innerHeight() / token_count) * 0.8 + "px";
							} else {
								return ($(this).innerWidth() / token_count) * 0.8 + "px";			
							}
						}
					}
				}).html(function(d) { 
					if(d.children) {
						return null;
					} else {
						var token = d["token_label"].split("");
						var token_count = token.length;
						var token_string = "";
						if($(this).innerHeight() > $(this).innerWidth()) {
							if(token_count > 1) {
								token.forEach(function(t) {
									token_string += t + "<br>";
								});
							} else {
								token_string = d.token_label;
							}
						} else {
							token_string =  d.token_label;
						}
						return token_string;
					}
				}).on("mouseover", function(d) {
					// Only show mouseover data for child nodes
					if(!d.children) {
						d3.select(this).style("border-width","2px");		
						if(lang === "en") {	
							treemap_tooltip.html(d.token_label + "  " + d.token_instance_ct + " occurrences");
						} else if(lang === "jp") {
							treemap_tooltip.html(d.token_label + "  " + d.token_instance_ct + "回");
					
						}
						var div_width = $(this).outerWidth();
						var position = $(this).position();
						treemap_tooltip.style("left", (position.left + div_width) + "px")	
							.style("right","auto")	
							.style("top", (position.top) + "px")
							.style("opacity",1);
					}
				}).on("mouseout", function(d) {
					treemap_tooltip.style("opacity",0);
					d3.select(this).style("border-width","0px");
				});
	
				node.transition()
					.duration(150)
					.delay(function(d,i) { return 50 + i*2; })
					.style("opacity",1);

			
			// Generate tooltip for treemap
			var treemap_tooltip = d3.select("#treemap_vis").append("div")	
				.attr("id","treemap_tooltip")
				.attr("class","tooltip")
				.style("opacity", 0);

			}
	

			// Bind toggle sorting for treemap
			d3.select("#toggle_sort_treemap").on("click", function() {
				console.log("ssdfgsdfg");
				if(dataset === "sorted") {
					dataset = "unsorted";
				} else if(dataset === "unsorted") {
					dataset = "sorted";
				}
		
				node.transition()
					.duration(250)
					.style("opacity",0)
					.each("start", function() { ++transition_ct; })
					.each("end", function() {
						d3.select(this).remove();
						if(!(--transition_ct)) {
							drawtree(dataset);
						}
					});
			});
	
			// Function called to position div elements for treemap
			function position() {
			  this.style("right", function(d) { return d.x + "px"; })
				  .style("top", function(d) { return d.y + "px"; })
				  .style("width", function(d) { return Math.max(0, d.dx - 1) + "px"; })
				  .style("height", function(d) { return Math.max(0, d.dy - 1) + "px"; });
			}
	
			// Draw key for main visualization
			draw_key("treemap");

			// Remove loading gif for treemap and make visible
			$("#treemap_vis .loading").remove();
			$("#treemap_vis_nav").css("visibility","visible");

		});	
			
		// Once all processes are complete, make all SVG elements visible
		$("#main_vis .loading").remove();
		$("#main_vis_nav").css("visibility","visible");
		svg.attr("opacity",1);
	
	});
	
</script>

</body>
</html>