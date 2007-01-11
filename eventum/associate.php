<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | Eventum - Issue Tracking System                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 2003, 2004, 2005, 2006, 2007 MySQL AB                        |
// |                                                                      |
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License as published by |
// | the Free Software Foundation; either version 2 of the License, or    |
// | (at your option) any later version.                                  |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to:                           |
// |                                                                      |
// | Free Software Foundation, Inc.                                       |
// | 59 Temple Place - Suite 330                                          |
// | Boston, MA 02111-1307, USA.                                          |
// +----------------------------------------------------------------------+
// | Authors: João Prado Maia <jpm@mysql.com>                             |
// +----------------------------------------------------------------------+
//
// @(#) $Id$
//
require_once("config.inc.php");
require_once(APP_INC_PATH . "db_access.php");
require_once(APP_INC_PATH . "class.template.php");
require_once(APP_INC_PATH . "class.auth.php");
require_once(APP_INC_PATH . "class.issue.php");
require_once(APP_INC_PATH . "class.note.php");
require_once(APP_INC_PATH . "class.support.php");
require_once(APP_INC_PATH . "class.mail.php");

$tpl = new Template_API();
$tpl->setTemplate("associate.tpl.html");

Auth::checkAuthentication(APP_COOKIE, 'index.php?err=5', true);

if (@$_POST['cat'] == 'associate') {
    if ($_POST['target'] == 'email') {
        $res = Support::associate(Auth::getUserID(), $_POST['issue'], $_POST['item']);
        if ($res == 1) {
            Workflow::handleManualEmailAssociation(Issue::getProjectID($_POST['issue']), $_POST['issue']);
        }
        $tpl->assign("associate_result", $res);
    } elseif ($_POST['target'] == 'reference') {
        $res = Support::associateEmail(Auth::getUserID(), $_POST['issue'], $_POST['item']);
        if ($res == 1) {
            Workflow::handleManualEmailAssociation(Issue::getProjectID($_POST['issue']), $_POST['issue']);
        }
        $tpl->assign("associate_result", $res);
    } else {
        for ($i = 0; $i < count($_POST['item']); $i++) {
            $email = Support::getEmailDetails(Email_Account::getAccountByEmail($_POST['item'][$i]), $_POST['item'][$i]);
            // add the message body as a note
            $_POST['blocked_msg'] = $email['seb_full_email'];
            $_POST['title'] = $email['sup_subject'];
            $_POST['note'] = $email['seb_body'];
            // XXX: probably broken to use the current logged in user as the 'owner' of 
            // XXX: this new note, but that's how it was already
            $res = Note::insert(Auth::getUserID(), $_POST['issue']);
            // remove the associated email
            if ($res) {
                list($_POST["from"]) = Support::getSender(array($_POST['item'][$i]));
                Workflow::handleBlockedEmail(Issue::getProjectID($_POST['issue']), $_POST['issue'], $_POST, 'associated');
                Support::removeEmail($_POST['item'][$i]);
            }
        }
        $tpl->assign("associate_result", $res);
    }
    @$tpl->assign('total_emails', count($_POST['item']));
} else {
    @$tpl->assign('emails', $_GET['item']);
    @$tpl->assign('total_emails', count($_GET['item']));
    $prj_id = Issue::getProjectID($_GET['issue']);
    if (Customer::hasCustomerIntegration($prj_id)) {
        // check if the selected emails all have sender email addresses that are associated with the issue' customer
        $senders = Support::getSender($_GET['item']);
        $sender_emails = array();
        for ($i = 0; $i < count($senders); $i++) {
            $email = Mail_API::getEmailAddress($senders[$i]);
            $sender_emails[$email] = $senders[$i];
        }
        $customer_id = Issue::getCustomerID($_GET['issue']);
        if (!empty($customer_id)) {
            $contact_emails = array_keys(Customer::getContactEmailAssocList($prj_id, $customer_id));
            $unknown_contacts = array();
            foreach ($sender_emails as $email => $address) {
                if (!@in_array($email, $contact_emails)) {
                    $usr_id = User::getUserIDByEmail($email);
                    if (empty($usr_id)) {
                        $unknown_contacts[] = $address;
                    } else {
                        // if we got a real user ID, check if the customer user is the correct one
                        // (i.e. a contact from the customer associated with the selected issue)
                        if (User::getRoleByUser($usr_id, $prj_id) == User::getRoleID('Customer')) {
                            // also check if the associated customer ID, if any, matches the one in the issue
                            $user_customer_id = User::getCustomerID($usr_id);
                            if ($user_customer_id != $customer_id) {
                                $unknown_contacts[] = $address;
                            }
                        }
                    }
                }
            }
            if (count($unknown_contacts) > 0) {
                $tpl->assign('unknown_contacts', $unknown_contacts);
            }
        }
    }
}

$tpl->assign("current_user_prefs", Prefs::get(Auth::getUserID()));

$tpl->displayTemplate();
?>