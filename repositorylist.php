<?php
/**
 * iF.SVNAdmin
 * Copyright (c) 2010 by Manuel Freiholz
 * http://www.insanefactory.com/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2
 * of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.
 */
include("include/config.inc.php");

//
// Authentication
//

$engine = \svnadmin\core\Engine::getInstance();

if (!$engine->isProviderActive(PROVIDER_REPOSITORY_VIEW)) {
	$engine->forwardError(ERROR_INVALID_MODULE);
}

$engine->checkUserAuthentication(true, ACL_MOD_REPO, ACL_ACTION_VIEW);
$appTR->loadModule("repositorylist");

//
// Actions
//

if (check_request_var("delete")) {
	$engine->handleAction("delete_repository");
}
else if (check_request_var('dump')) {
	$engine->handleAction('dump_repository');
	exit(0);
}
else if (check_request_var('load')) {
	
	exit(0);
}

$currUserName = $appEngine->getSessionUsername();
$currUser = new \svnadmin\core\entities\User;
$currUser->id = $currUserName;
$currUser->name = $currUserName;
$isAdmin = $appEngine->getAclManager()->isUserAdmin($currUser);
if (!$isAdmin) {
	// Get visible repository list of non admin user
	$visibleRepos = null;

	// Get user readable access paths
	$readablePaths = null;
	if ($appEngine->checkUserAuthentication(false, ACL_MOD_ACCESSPATH, ACL_ACTION_VIEW)) {
		$readablePaths = $appEngine->getAccessPathViewProvider()->getPathsOfUser($currUser);
		foreach ($readablePaths as $accessPath) {
			$splits = explode(':', $accessPath->getPath(), 2);
			if (count($splits) > 1) {
				$visibleRepos[$splits[0]] = true;
			}
		}
	}

	// Get user managed access paths
	$managedPaths = null;
	if ($appEngine->getAclManager()->isUserAccessPathManager($currUserName)) {
		$managedPaths = $appEngine->getAclManager()->getAccessPathsOfUser($currUserName);
		foreach ($managedPaths as $accessPath) {
			$splits = explode(':', $accessPath->getPath(), 2);
			if (count($splits) > 1) {
				$visibleRepos[$splits[0]] = true;
			}
		}
	}
}

//
// View data
//

$repositoryParentList = array();
$repositoryList = array();
try {
	// Repository parent locations.
	$repositoryParentList = $engine->getRepositoryViewProvider()->getRepositoryParents();
	
	// Repositories of all locations.
	foreach ($repositoryParentList as $rp) {
		$allRepos = $engine->getRepositoryViewProvider()->getRepositoriesOfParent($rp);
		$filteredRepos = array();
		foreach ($allRepos as $repo) {
			if ($isAdmin || isset($visibleRepos[$repo->getName()])) {
				array_push($filteredRepos, $repo);
			}
		}
		$repositoryList[$rp->identifier] = $filteredRepos;
		usort($repositoryList[$rp->identifier], array('\svnadmin\core\entities\Repository', 'compare'));
	}
	
	// Show options column?
	if (($engine->isProviderActive(PROVIDER_REPOSITORY_EDIT)
		&& $engine->hasPermission(ACL_MOD_REPO, ACL_ACTION_DUMP)
		&& $engine->getConfig()->getValueAsBoolean('GUI', 'RepositoryDumpEnabled', false))
		){
		SetValue('ShowOptions', true);
		SetValue('ShowDumpOption', true);
	}
}
catch (Exception $ex) {
	$engine->addException($ex);
}

SetValue('RepositoryParentList', $repositoryParentList);
SetValue('RepositoryList', $repositoryList);
SetValue('ShowDeleteButton', $engine->getConfig()->getValueAsBoolean('GUI', 'RepositoryDeleteEnabled', true));
ProcessTemplate('repository/repositorylist.html.php');
?>
