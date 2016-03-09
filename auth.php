<?php

namespace Sabre\DAV\Auth\Backend;

use Sabre\DAV;
use Sabre\DAV\MkCol;

/**
 * This is an authentication backend for ZeyOS
 *
 * @copyright Copyright (C) 2016 ZeyOS, Inc. (https://www.zeyos.com)
 * @author Peter-Christoph Haider
 * @license LGPL
 */
class ZeyOS extends AbstractBasic {
	private $instanceId;
	private $principalBackend = false;
	private $db = false;

	/**
	 * Creates the backend object.
	 *
	 * If the filename argument is passed in, it will parse out the specified file fist.
	 *
	 * @param string|null $filename
	 */
	function __construct($instanceId, $principalBackend=false, $dbBackend) {
		$this->instanceId = $instanceId;
		$this->principalBackend = $principalBackend;
		$this->db = $dbBackend;
	}

	/**
	 * Validates a username and password
	 *
	 * This method should return true or false depending on if login
	 * succeeded.
	 *
	 * @param string $username
	 * @param string $password
	 * @return bool
	 */
	protected function validateUserPass($username, $password) {
		// Authenticate through trusted salt
		if (defined('TRUSTED_SALT') && $password == md5(TRUSTED_SALT.$username)) {
			return true;
		}

		// Authenticate through trusted host
		if (defined('TRUSTED_HOST') && TRUSTED_HOST != '' && isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] == TRUSTED_HOST) {
			return true;
		}

		$req = new \REST\Client('https://api.zeyos.com/'.$this->instanceId.'/1.0/auth');
		$json = $req->post([
			'user' => $username,
			'password' => $password,
			'identifier' => 'dav.'.$this->instanceId
		]);

		$res = json_decode($json, true);
		if (!isset($res['result'])) {
			return false;
		}

		// Auto-provisioning
		if ($this->principalBackend) {
			// Check if the principal exists
			$principal = $this->principalBackend->getPrincipalByPath('principals/' . $this->normalizeName($username));
			if (!$principal) {
				// Get the user details
				$req = new \REST\Client('https://api.zeyos.com/'.$this->instanceId.'/1.0/auth');
				$req->appendHeader('Authorization: ZeyOS-Token '.$res['result'].' dav.'.$this->instanceId);
				$json = $req->get();
				$res = json_decode($json, true);

				if (isset($res['result'])) {
					$user = $res['result'];
					$properties = [];
					if (isset($user['contact']) && isset($user['contact']['lastname']) && isset($user['contact']['firstname']))
						$properties['{DAV:}displayname'] = $user['contact']['firstname'] . ' ' . $user['contact']['lastname'];
					if (isset($user['email']))
						$properties['{http://sabredav.org/ns}email-address'] = $user['email'];

					$this->createPrincipalGroup($this->normalizeName($username), $properties);
				}
			}
		}

		return true;
	}

	protected function createPrincipalGroup($uri, $properties=[]) {
		$uri = 'principals/' . $uri;
		$resourceType = ['{DAV:}collection', '{urn:ietf:params:xml:ns:caldav}calendar'];

		$mkCol = new MkCol($resourceType, $properties);
		$this->principalBackend->createPrincipal($uri, $mkCol);
		$mkCol->commit();

		$mkCol = new MkCol($resourceType, []);
		$this->principalBackend->createPrincipal($uri . '/calendar-proxy-read', $mkCol);
		$mkCol->commit();

		$mkCol = new MkCol($resourceType, []);
		$this->principalBackend->createPrincipal($uri . '/calendar-proxy-write', $mkCol);
		$mkCol->commit();
	}

	/**
	 * @param array $groups {"groupname": {"user1": INT, "user2": INT, ...}, ...}
	 */
	public function provisionZeyOSGroups($groups, $users) {
		$nodes = ['addressbooks' => 'address book', 'calendars' => 'calendar'];

		foreach ($users as $user) {
			$uri = $this->normalizeName($user['name']);
			$principal = $this->principalBackend->getPrincipalByPath('principals/' . $uri);
			if (!$principal) {
				$properties = [];
				if (isset($user['lastname']) && isset($user['firstname']))
					$properties['{DAV:}displayname'] = $user['firstname'] . ' ' . $user['lastname'];
				if (isset($user['email']))
					$properties['{http://sabredav.org/ns}email-address'] = $user['email'];

				echo 'Adding new user principal '.$uri."\n";
				$this->createPrincipalGroup($uri, $properties);
			}
		}

		$users = [];
		foreach ($groups as $groupname => $members) {
			// Each group receives a principal with the URI <instanceid>-<groupname>
			$uri = $this->instanceId . '-' . $this->normalizeName($groupname);

			// Check, if the system principal exists
			$principal = $this->principalBackend->getPrincipalByPath('principals/' . $uri);
			if (!$principal) {
				echo 'Adding new group principal '.$uri."\n";
				$this->createPrincipalGroup($uri);
			}

			// Get the read/write principals for delegation
			$principalSysRead  = $this->getPrincipal($uri . '/calendar-proxy-read');
			$principalSysWrite = $this->getPrincipal($uri . '/calendar-proxy-write');

			// Create the calendar and address book
			foreach ($nodes as $node => $label) {
				if (!$this->getNode($node, $uri, $this->normalizeName($groupname))) {
					echo 'Adding new group '.$label.' '.$uri."\n";
					$calendar = $this->createNode($node, $uri, $this->normalizeName($groupname), 'Group calendar for '.$groupname);
				}
			}

			// Delegate the calendar permissions
			foreach ($members as $username => $writable) {
				$writable = (bool) $writable;
				if (!isset($users[$username])) {
					$user = $this->getPrincipal($username);
					if (is_array($user))
						$users[$username] = $user;
				}
				if (isset($users[$username])) {
					if (!$this->checkCalendarMember($users[$username]['id'], $writable ? $principalSysWrite['id'] : $principalSysRead['id'])) {
						echo 'Provision '.$username.' for group '.$groupname."\n";
						$this->addCalendarMember($users[$username]['id'], $writable ? $principalSysWrite['id'] : $principalSysRead['id']);
					}
				}
			}
		}
	}

	protected function getPrincipal($uri) {
		return $this->db->table('principals')->getOneBy('uri', 'principals/'.$uri);
	}

	protected function getNode($entity, $username, $uri) {
		$res = $this->db->select(
			'*',
			$entity,
			$this->db->where('principaluri', 'principals/'.$username)
			.' AND '
			.$this->db->where('uri', $uri)
		);

		if (is_array($res))
			return array_pop($res);

		return false;
	}

	protected function createNode($entity, $username, $uri, $displayname='') {
		// Create the calendar
		$this->db->table($entity)->insert([
			'principaluri' => 'principals/'.$username,
			'uri'          => $uri,
			'displayname'  => $displayname
		]);
	}

	protected function getCalendar($username, $uri) {
		return $this->getNode('calendars', $username, $uri);
	}

	protected function createCalendar($username, $uri, $displayname='') {
		$this->getNode('calendars', $username, $uri, $displayname);
	}

	protected function getAddressBook($username, $uri) {
		return $this->getNode('addressbooks', $username, $uri);
	}

	protected function createAddressBook($username, $uri, $displayname='') {
		$this->getNode('addressbooks', $username, $uri, $displayname);
	}

	protected function checkCalendarMember($member_id, $principal_id) {
		$res = $this->db->select(
			'*',
			'groupmembers',
			$this->db->where('member_id', $member_id)
			.' AND '
			.$this->db->where('principal_id', $principal_id)
		);

		return is_array($res) && $res;
	}

	protected function addCalendarMember($member_id, $principal_id) {
		// Create the calendar
		$this->db->table('groupmembers')->insert([
			'member_id'    => $member_id,
			'principal_id' => $principal_id
		]);
	}

	protected function normalizeName($name) {
		return preg_replace('/[^a-z0-9_\.-]/', '', str_replace(' ', '-', strtolower($name)));
	}
}

