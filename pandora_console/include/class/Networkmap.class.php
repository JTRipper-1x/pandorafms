<?php

// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2011 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the  GNU Lesser General Public License
// as published by the Free Software Foundation; version 2

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.



/**
 * @package Include
 * @subpackage Networkmap
 */

require_once ('include/functions_os.php');
require_once ('include/functions_networkmap.php');
enterprise_include("include/functions_networkmap_enterprise.php");

require_once("include/class/Map.class.php");

class Networkmap extends Map {
	protected $filter = array();
	
	protected $source_group = 0;
	protected $source_ip_mask = "";
	
	public function __construct($id) {
		parent::__construct($id);
		
		$this->requires_js[] = "include/javascript/map/NetworkmapController.js";
	}
	
	public function processDBValues($dbValues) {
		$filter = json_decode($dbValues['filter'], true);
		
		$this->filter = $filter;
		if (!isset($this->filter['only_snmp_modules']))
			$this->filter['only_snmp_modules'] = false;
		
		switch ($dbValues['source_data']) {
			case MAP_SOURCE_GROUP:
				$this->source_group = $dbValues['source'];
				$this->source_ip_mask = "";
				break;
			case MAP_SOURCE_IP_MASK:
				$this->source_group = $dbValues['source'];
				$this->source_ip_mask = "";
				break;
		}
		
		parent::processDBValues($dbValues);
	}
	
	protected function generateDot($graph, $positions) {
		$graph = preg_replace('/^graph .*/', '', $graph);
		
		$nodes_and_edges = explode("];", $graph);
		
		$nodes = array();
		$edges = array();
		$last_graph_id = 0;
		foreach ($nodes_and_edges as $node_or_edge) {
			$node_or_edge = trim($node_or_edge);
			
			$chunks = explode("[", $node_or_edge);
			
			if (strstr($chunks[0], "--") !== false) {
				// EDGE
				$graphviz_ids = explode("--", $chunks[0]);
				
				$edges[] = array(
					'to' => trim($graphviz_ids[0]),
					'from' => trim($graphviz_ids[1]));
			}
			else {
				// NODE
				$graphviz_id = trim($chunks[0]);
				
				// Avoid the weird nodes.
				if (!is_numeric($graphviz_id))
					continue;
				
				
				$chunks = explode("ajax.php?", $chunks[1]);
				
				if (strstr($chunks[1], "&id_module=") !== false) {
					// MODULE
					preg_match("/id_module=([0-9]*)/", $chunks[1], $matches);
					$id = $matches[1];
					$type = ITEM_TYPE_MODULE_NETWORKMAP;
				}
				else {
					// AGENT
					preg_match("/id_agent=([0-9]*)/", $chunks[1], $matches);
					$id = $matches[1];
					$type = ITEM_TYPE_AGENT_NETWORKMAP;
				}
				
				$nodes[] = array('graph_id' => $graphviz_id,
					'id' => $id, 'type' => $type);
				
				if ($last_graph_id < $graphviz_id)
					$last_graph_id = $graphviz_id;
			}
		}
		
		
		
		foreach ($positions as $line) {
			//clean line a long spaces for one space caracter
			$line = preg_replace('/[ ]+/', ' ', $line);
			
			if (preg_match('/^node.*$/', $line) != 0) {
				$items = explode(' ', $line);
				$graphviz_id = $items[1];
				
				// We need a binary tree...in some future time.
				
				foreach ($nodes as $i => $node) {
					if ($node['graph_id'] == $graphviz_id) {
						$nodes[$i]['x'] = $items[2] * 100; //200 is for show more big
						$nodes[$i]['y'] = $items[3] * 100;
					}
				}
			}
		}
		
		foreach ($edges as $i => $edge) {
			$graph_id = ++$last_graph_id;
			
			$nodes[] = array(
				'graph_id' => $graph_id,
				'type' => ITEM_TYPE_EDGE_NETWORKMAP);
			$edges[$i]['graph_id'] = $graph_id;
			
		}
		
		$this->nodes = $nodes;
		$this->edges = $edges;
	}
	
	protected function temp_parseParameters_generateDot() {
		$return = array();
		
		$return['id_group'] = $this->source_group;
		$return['simple'] = 0; // HARD CODED
		$return['font_size'] = null;
		$return['layout'] = null;
		$return['nooverlap'] = false; // HARD CODED
		$return['zoom'] = 1; // HARD CODED
		$return['ranksep'] = 2.5; // HARD CODED
		$return['center'] = 0; // HARD CODED
		$return['regen'] = 0; // HARD CODED
		$return['pure'] = 0; // HARD CODED
		$return['id'] = $this->id;
		$return['show_snmp_modules'] = $this->filter['only_snmp_modules'];
		$return['l2_network_interfaces'] = true; // HARD CODED
		$return['ip_mask'] = $this->source_ip_mask;
		$return['dont_show_subgroups'] = false;
		$return['old_mode'] = false;
		$return['filter'] = $this->filter['text'];
		
		return $return;
	}
	
	protected function getNodes() {
		if (empty($this->nodes)) {
			
			// ----- INI DEPRECATED CODE--------------------------------
			//  I hope this code to change for any some better and
			//  rewrote the old function.
			
			$parameters = $this->temp_parseParameters_generateDot();
			
			// Generate dot file
			$graph = networkmap_generate_dot (__('Pandora FMS'),
				$parameters['id_group'],
				$parameters['simple'],
				$parameters['font_size'],
				$parameters['layout'],
				$parameters['nooverlap'],
				$parameters['zoom'],
				$parameters['ranksep'],
				$parameters['center'],
				$parameters['regen'],
				$parameters['pure'],
				$parameters['id'],
				$parameters['show_snmp_modules'],
				false, //cut_names
				true, // relative
				'',
				$parameters['l2_network_interfaces'],
				$parameters['ip_mask'],
				$parameters['dont_show_subgroups'],
				false,
				null,
				$parameters['old_mode']);
			
			
			
			$filename_dot = sys_get_temp_dir() . "/networkmap" . uniqid() . ".dot";
			
			file_put_contents($filename_dot, $graph);
			
			$filename_plain = sys_get_temp_dir() . "/plain.txt";
			
			switch ($this->generation_method) {
				case MAP_GENERATION_CIRCULAR:
					$graphviz_command = "circo";
					break;
				case MAP_GENERATION_PLANO:
					$graphviz_command = "dot";
					break;
				case MAP_GENERATION_RADIAL:
					$graphviz_command = "twopi";
					break;
				case MAP_GENERATION_SPRING1:
					$graphviz_command = "spring1";
					break;
				case MAP_GENERATION_SPRING2:
					$graphviz_command = "spring2";
					break;
			}
			
			$cmd = "$graphviz_command " .
			"-Tpng -o /tmp/caca.png -Tplain -o " . $filename_plain . " " .
				$filename_dot;
			
			system ($cmd);
			
			
			
			$this->generateDot($graph, file($filename_plain));
			
			unlink($filename_dot);
			unlink($filename_plain);
			// ----- END DEPRECATED CODE--------------------------------
			
			switch (get_class($this)) {
				case 'NetworkmapEnterprise':
					NetworkmapEnterprise::dbSaveNodes();
					break;
			}
		}
	}
	
	public function show() {
		$this->getNodes();
		
		parent::show();
	}
	
	public function printJSInit() {
		echo "<h1>Networkmap</h1>";
		?>
		<script type="text/javascript">
			$(function() {
				// map = new js_networkmap();
			});
		</script>
		<?php
	}
}
?>