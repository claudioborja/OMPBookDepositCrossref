<?php
/**
 * @file plugins/importexport/OMPBookDepositCrossref/index.php
 *
 * Copyright (c) 2026
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_importexport_OMPBookDepositCrossref
 * @brief Wrapper for the Crossref Book Deposit plugin.
 */

// Este archivo le dice al sistema cómo cargar e iniciar la clase del plugin.
require_once('OMPBookDepositCrossrefPlugin.php');
return new \APP\plugins\importexport\OMPBookDepositCrossref\OMPBookDepositCrossrefPlugin();
