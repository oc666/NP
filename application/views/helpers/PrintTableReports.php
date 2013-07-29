<?php
/**
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
class Zend_View_Helper_PrintTableReports extends Zend_View_Helper_Abstract {

	public function printTableReports($rows) {
		if (is_array($rows) && count($rows)) {
			
			echo '<div><table>';
			echo '<tr style="border:solid 1px;font-weight:bold;">';
			if(isset($rows[0])){
			foreach ($rows[0] as $key => $row) {
				echo '<td style="border:solid 1px;">' . $key . '</td>';
			}
			}
			else{
			foreach ($rows as $key => $row) {
				echo '<td style="border:solid 1px;">' . $key . '</td>';
			}	
			}
			echo '</tr>';
				echo '<tr style="border:solid 1px;">';
			foreach ($rows as $row => $val) {
//				var_dump($val);
//				die;

				
					if ($val !== NULL) {
						echo '<td style="border:solid 1px;">' . $val . '</td>';
					} else {
						echo '<td style="border:solid 1px;">&nbsp;</td>';
					}
				
			
			}
				echo '</tr>';
			echo '</table></div>';
		}
	}

}