<?php
/**
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
class Application_Form_RejectForm extends Zend_Form
{
    public function init()
    {
		
		$this->setMethod('POST');
		$this->setAction('');
		$this->addElement('text', 'reject_reason_code', array(
            'label'      => 'reject_reason_code',
            'required'   => true
			
        )); 
		
		// Add the submit button
        $this->addElement('submit', 'submit', array(
            'ignore'   => true,
            'label'    => 'submit',
        ));
 
        
    }
}
