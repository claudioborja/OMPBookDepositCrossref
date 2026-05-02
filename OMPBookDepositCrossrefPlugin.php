<?php

namespace APP\plugins\importexport\OMPBookDepositCrossref;

use PKP\plugins\ImportExportPlugin;
use PKP\core\PKPApplication;
use APP\template\TemplateManager;

class OMPBookDepositCrossrefPlugin extends ImportExportPlugin
{
    /**
     * @see Plugin::register()
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled()) {
            $this->addLocaleData();
        }
        return $success;
    }

    /**
     * Get the name of this plugin. The name must be unique within
     * its category.
     * @return String name of plugin
     */
    public function getName()
    {
        return 'OMPBookDepositCrossrefPlugin';
    }

    public function getDisplayName()
    {
        return __('plugins.importexport.OMPBookDepositCrossref.displayName');
    }

    public function getDescription()
    {
        return __('plugins.importexport.OMPBookDepositCrossref.description');
    }

    /**
     * @see ImportExportPlugin::display()
     * Aquí programaremos la vista gráfica que tendrás en OMP
     */
    public function display($args, $request)
    {
        parent::display($args, $request);
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();
        
        $opType = array_shift($args);
        
        switch ($opType) {
            case 'index':
            case '':
                $apiUrl = $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_API, $context->getPath(), 'submissions');
                $submissionsListPanel = new \APP\components\listPanels\SubmissionsListPanel(
                    'submissions',
                    __('common.publications'),
                    [
                        'apiUrl' => $apiUrl,
                        'count' => 100,
                        'getParams' => ['status' => 3], // Solo publicados
                        'lazyLoad' => true,
                    ]
                );
                $submissionsConfig = $submissionsListPanel->getConfig();
                if (isset($submissionsConfig['filters'])) {
                    $submissionsConfig['filters'] = array_slice($submissionsConfig['filters'], 1);
                }
                
                $templateMgr->setState([
                    'components' => [
                        'submissions' => $submissionsConfig,
                    ],
                ]);
                $templateMgr->assign([
                    'pageComponent' => 'ImportExportPage',
                    'savedEnvironment' => $this->getSetting($context->getId(), 'crossrefEnvironment') ?: 'test',
                    'savedLoginId'     => $this->getSetting($context->getId(), 'crossrefLoginId') ?: '',
                    'savedLoginPasswd' => $this->getSetting($context->getId(), 'crossrefLoginPasswd') ?: '',
                    'settingsSaved'    => (bool) $request->getUserVar('settingsSaved'),
                ]);
                $templateMgr->display($this->getTemplateResource('index.tpl'));
                break;
                
            case 'exportSubmissionsBounce':
                // Aquí engancharemos la logica de generacion XML
                $selectedSubmissions = (array) $request->getUserVar('selectedSubmissions');
                if (empty($selectedSubmissions)) {
                    $request->redirect(null, null, null, ['plugin', $this->getName()]);
                }
                
                $this->exportSubmissions($selectedSubmissions, $context, $request);
                break;

            case 'depositSubmissionsBounce':
                $selectedSubmissions = (array) $request->getUserVar('selectedSubmissions');
                if (empty($selectedSubmissions)) {
                    $request->redirect(null, null, null, ['plugin', $this->getName()]);
                }

                $this->depositSubmissions($selectedSubmissions, $context, $request);
                break;

            case 'saveSettingsBounce':
                $this->saveSettings($context, $request);
                $request->redirect(null, null, null, ['plugin', $this->getName()], ['settingsSaved' => 1]);
                break;

            default:
                $dispatcher = $request->getDispatcher();
                $dispatcher->handle404();
                break;
        }
    }

    /**
     * Lógica de generación de XML para Libros y Capítulos
     */
    protected function exportSubmissions($submissionIds, $context, $request)
    {
        $xml = $this->buildCrossrefBatchXml($submissionIds, $context, $request);
        $this->outputXmlDownload($xml);
    }

    /**
     * Build Crossref XML body for selected submissions.
     */
    protected function buildCrossrefBatchXml($submissionIds, $context, $request)
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<doi_batch version=\"5.4.0\" xmlns=\"http://www.crossref.org/schema/5.4.0\" xmlns:jats=\"http://www.ncbi.nlm.nih.gov/JATS1\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.crossref.org/schema/5.4.0 https://www.crossref.org/schemas/crossref5.4.0.xsd\">\n";

        $xml .= "  <head>\n";
        $timestamp = date('YmdHis') . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
        $xml .= "    <doi_batch_id>omp_" . $timestamp . "</doi_batch_id>\n";
        $xml .= "    <timestamp>" . $timestamp . "</timestamp>\n";
        $xml .= "    <depositor>\n";
        $depositorName = $context->getData('publisherInstitution') ?: $context->getLocalizedName();
        $xml .= "      <depositor_name>" . $this->xmlEscape($depositorName) . "</depositor_name>\n";
        $xml .= "      <email_address>" . $this->xmlEscape((string) $context->getData('contactEmail')) . "</email_address>\n";
        $xml .= "    </depositor>\n";
        $xml .= "    <registrant>" . $this->xmlEscape($depositorName) . "</registrant>\n";
        $xml .= "  </head>\n";
        $xml .= "  <body>\n";

        $chapterDao = \PKP\db\DAORegistry::getDAO('ChapterDAO');

        foreach ($submissionIds as $submissionId) {
            $submission = \APP\facades\Repo::submission()->get((int)$submissionId);
            if (!$submission) continue;

            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;

            $datePublished = $publication->getData('datePublished');
            $bookDoi = trim((string) $publication->getDoi());

            $chaptersResult = $chapterDao->getByPublicationId($publication->getId());
            $chapters = [];
            $hasChapterDoi = false;
            while ($chapter = $chaptersResult->next()) {
                $chapters[] = $chapter;
                if (trim((string) $chapter->getDoi()) !== '') {
                    $hasChapterDoi = true;
                }
            }

            // Export only records that have at least one DOI to register.
            if ($bookDoi === '' && !$hasChapterDoi) {
                continue;
            }

            $publicationLocale = $publication->getData('locale') ?: $context->getPrimaryLocale();
            $bookLanguage = $this->getLanguageCode($publicationLocale);

            // Detect OMP series; use book_series_metadata when series has an ISSN (required by XSD).
            $seriesId = $publication->getData('seriesId');
            $series = null;
            if ($seriesId) {
                $series = \APP\facades\Repo::section()->get((int) $seriesId);
                if ($series && !$series->getOnlineISSN() && !$series->getPrintISSN()) {
                    $series = null; // ISSN required for series_metadata — fall back to book_metadata
                }
            }

            $workType = $submission->getData('workType');
            $bookType = ($workType === \APP\submission\Submission::WORK_TYPE_EDITED_VOLUME) ? 'edited_book' : 'monograph';

            $xml .= "    <book book_type=\"{$bookType}\">\n";

            if ($series) {
                $xml .= "      <book_series_metadata language=\"{$bookLanguage}\">\n";
                $xml .= "        <series_metadata>\n";
                $xml .= "          <titles>\n";
                $xml .= "            <title>" . $this->xmlEscape($series->getLocalizedTitle()) . "</title>\n";
                $xml .= "          </titles>\n";
                // Emit ISSNs without media_type to match Crossref webform reference format.
                if ($series->getPrintISSN()) {
                    $xml .= "          <issn>" . $this->xmlEscape($series->getPrintISSN()) . "</issn>\n";
                }
                if ($series->getOnlineISSN()) {
                    $xml .= "          <issn>" . $this->xmlEscape($series->getOnlineISSN()) . "</issn>\n";
                }
                $xml .= "        </series_metadata>\n";
            } else {
                $xml .= "      <book_metadata language=\"{$bookLanguage}\">\n";
            }
            
            // Contributors: editors for edited volumes, authors otherwise
            $bookContributorRole = ($workType === \APP\submission\Submission::WORK_TYPE_EDITED_VOLUME) ? 'editor' : 'author';
            $xml .= $this->buildContributorsXml($publication->getData('authors'), '        ', $bookContributorRole);

            // Title
            $xml .= "        <titles>\n";
            $xml .= "          <title>" . $this->xmlEscape($publication->getLocalizedTitle()) . "</title>\n";
            if ($publication->getLocalizedSubTitle()) {
                $xml .= "          <subtitle>" . $this->xmlEscape($publication->getLocalizedSubTitle()) . "</subtitle>\n";
            }
            $xml .= "        </titles>\n";

            // Abstract with xml:lang
            $xml .= $this->buildJatsAbstractXml($publication->getLocalizedData('abstract'), '        ', $publicationLocale);

            // Volume number (from series position, only valid in book_series_metadata)
            if ($series) {
                $seriesPosition = trim((string) $publication->getData('seriesPosition'));
                if ($seriesPosition !== '') {
                    $xml .= "        <volume>" . $this->xmlEscape($seriesPosition) . "</volume>\n";
                }
            }

            // Publication date
            $xml .= $this->buildPublicationDateXml($datePublished, '        ', 'online');
            
            // ISBN (from publication formats) and Publisher
            $isbns = $this->collectIsbnsFromPublicationFormats($publication, $context);
            if (empty($isbns)) {
                $xml .= "        <noisbn reason=\"monograph\" />\n";
            } else {
                foreach ($isbns as $isbn) {
                    $xml .= "        <isbn media_type=\"" . $this->xmlEscape($isbn['mediaType']) . "\">" . $this->xmlEscape($isbn['value']) . "</isbn>\n";
                }
            }
            $xml .= "        <publisher>\n";
            $xml .= "          <publisher_name>" . $this->xmlEscape($depositorName) . "</publisher_name>\n";
            $xml .= "        </publisher>\n";
            
            // DOI for the book
            if ($bookDoi !== '') {
                $xml .= "        <doi_data>\n";
                $xml .= "          <doi>" . $this->xmlEscape($bookDoi) . "</doi>\n";
                $url = $request->url(null, 'catalog', 'book', [$submissionId]);
                $xml .= "          <resource>" . $this->xmlEscape($url) . "</resource>\n";
                $xml .= "        </doi_data>\n";
            }

            // Book citations (from DB: parsed citations table and/or citationsRaw)
            // citation_list is only valid in book_metadata, not book_series_metadata.
            if (!$series) {
                $xml .= $this->buildCitationListXmlForPublication($publication, '        ');
            }

            if ($series) {
                $xml .= "      </book_series_metadata>\n";
            } else {
                $xml .= "      </book_metadata>\n";
            }
            
            // Chapters
            foreach ($chapters as $chapter) {
                $chapterDoi = trim((string) $chapter->getDoi());
                if ($chapterDoi === '') {
                    continue;
                }

                $chapterLocale = $chapter->getData('locale') ?: $publicationLocale;
                $chapterLanguage = $this->getLanguageCode($chapterLocale);
                $xml .= "      <content_item component_type=\"chapter\" language=\"{$chapterLanguage}\">\n";

                // XSD sequence for content_item:
                // contributors → titles → abstract → component_number → publication_date → pages → doi_data

                // Chapter contributors
                $xml .= $this->buildContributorsXml($chapter->getAuthors(), '        ');

                // Chapter Title
                $xml .= "        <titles>\n";
                $xml .= "          <title>" . $this->xmlEscape($chapter->getLocalizedTitle()) . "</title>\n";
                if ($chapter->getLocalizedSubtitle()) {
                    $xml .= "          <subtitle>" . $this->xmlEscape($chapter->getLocalizedSubtitle()) . "</subtitle>\n";
                }
                $xml .= "        </titles>\n";

                // Chapter abstract with xml:lang
                $xml .= $this->buildJatsAbstractXml($chapter->getLocalizedAbstract(), '        ', $chapterLocale);

                // Chapter sequence number (after abstract per XSD)
                $chapterSequence = $chapter->getSequence();
                if ($chapterSequence !== null && $chapterSequence !== '') {
                    $xml .= "        <component_number>" . $this->xmlEscape((string) $chapterSequence) . "</component_number>\n";
                }

                // Chapter date (chapter-specific if available, otherwise book date)
                $chapterDate = $chapter->getDatePublished() ?: $datePublished;
                $xml .= $this->buildPublicationDateXml($chapterDate, '        ', 'online');

                // Chapter pages
                $xml .= $this->buildPagesXml($chapter->getPages(), '        ');
                
                // Chapter DOI
                $xml .= "        <doi_data>\n";
                $xml .= "          <doi>" . $this->xmlEscape($chapterDoi) . "</doi>\n";
                $url = $request->url(null, 'catalog', 'book', [$submissionId, 'chapter', $chapter->getId()]);
                $xml .= "          <resource>" . $this->xmlEscape($url) . "</resource>\n";
                $xml .= "        </doi_data>\n";
                
                $xml .= "      </content_item>\n";
            }
            
            $xml .= "    </book>\n";
        }

        $xml .= "  </body>\n";
        $xml .= "</doi_batch>\n";

        return $xml;
    }

    /**
     * Output generated XML as a downloadable file.
     */
    protected function outputXmlDownload($xml)
    {
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="crossref_books_'.time().'.xml"');
        header('Cache-Control: public');

        echo $xml;
        exit;
    }

    /**
     * Persist Crossref credentials and environment in plugin settings.
     */
    protected function saveSettings($context, $request)
    {
        $environment = $request->getUserVar('crossrefEnvironment') === 'live' ? 'live' : 'test';
        $loginId     = trim((string) $request->getUserVar('crossrefLoginId'));
        $loginPasswd = trim((string) $request->getUserVar('crossrefLoginPasswd'));

        $this->updateSetting($context->getId(), 'crossrefEnvironment', $environment, 'string');
        $this->updateSetting($context->getId(), 'crossrefLoginId', $loginId, 'string');

        // Only overwrite stored password when user actively sends a new non-empty value.
        if ($loginPasswd !== '') {
            $this->updateSetting($context->getId(), 'crossrefLoginPasswd', $loginPasswd, 'string');
        }
    }

    /**
     * Deposit selected submissions directly to Crossref.
     */
    protected function depositSubmissions($submissionIds, $context, $request)
    {
        $environment = trim((string) $request->getUserVar('crossrefEnvironment'));
        if ($environment !== 'live') {
            $environment = 'test';
        }

        $loginId     = trim((string) $request->getUserVar('crossrefLoginId'));
        $loginPasswd = trim((string) $request->getUserVar('crossrefLoginPasswd'));

        // Fall back to saved settings when the form fields arrive empty.
        if ($loginId === '') {
            $loginId = (string) $this->getSetting($context->getId(), 'crossrefLoginId');
        }
        if ($loginPasswd === '') {
            $loginPasswd = (string) $this->getSetting($context->getId(), 'crossrefLoginPasswd');
        }

        if ($loginId === '' || $loginPasswd === '') {
            $this->renderDepositResult([
                'ok' => false,
                'message' => __('plugins.importexport.OMPBookDepositCrossref.depositMissingCredentials'),
                'environment' => $environment,
                'endpoint' => '',
                'httpStatus' => null,
                'responseBody' => '',
            ]);
        }

        $xml = $this->buildCrossrefBatchXml($submissionIds, $context, $request);
        $result = $this->depositToCrossref($xml, $loginId, $loginPasswd, $environment);
        $this->renderDepositResult($result);
    }

    /**
     * Perform Crossref deposit using servlet endpoint.
     */
    protected function depositToCrossref($xml, $loginId, $loginPasswd, $environment = 'test')
    {
        $endpoint = ($environment === 'live')
            ? 'https://doi.crossref.org/servlet/deposit'
            : 'https://test.crossref.org/servlet/deposit';

        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'message' => __('plugins.importexport.OMPBookDepositCrossref.depositCurlUnavailable'),
                'environment' => $environment,
                'endpoint' => $endpoint,
                'httpStatus' => null,
                'responseBody' => '',
            ];
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'crossref_');
        if ($tempFile === false) {
            return [
                'ok' => false,
                'message' => __('plugins.importexport.OMPBookDepositCrossref.depositTempFileError'),
                'environment' => $environment,
                'endpoint' => $endpoint,
                'httpStatus' => null,
                'responseBody' => '',
            ];
        }

        file_put_contents($tempFile, $xml);

        $postFields = [
            'operation' => 'doMDUpload',
            'login_id' => $loginId,
            'login_passwd' => $loginPasswd,
            'fname' => new \CURLFile($tempFile, 'application/xml', 'crossref_books_' . time() . '.xml'),
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        @unlink($tempFile);

        if ($responseBody === false) {
            return [
                'ok' => false,
                'message' => __('plugins.importexport.OMPBookDepositCrossref.depositConnectionError') . ' ' . $curlError,
                'environment' => $environment,
                'endpoint' => $endpoint,
                'httpStatus' => $httpStatus ?: null,
                'responseBody' => '',
            ];
        }

        return [
            'ok' => ($httpStatus >= 200 && $httpStatus < 300),
            'message' => ($httpStatus >= 200 && $httpStatus < 300)
                ? __('plugins.importexport.OMPBookDepositCrossref.depositSuccess')
                : __('plugins.importexport.OMPBookDepositCrossref.depositHttpError'),
            'environment' => $environment,
            'endpoint' => $endpoint,
            'httpStatus' => $httpStatus,
            'responseBody' => (string) $responseBody,
        ];
    }

    /**
     * Render a simple result page after Crossref deposit.
     */
    protected function renderDepositResult($result)
    {
        header('Content-Type: text/html; charset=UTF-8');

        $statusLabel = $result['ok']
            ? __('plugins.importexport.OMPBookDepositCrossref.depositStatusOk')
            : __('plugins.importexport.OMPBookDepositCrossref.depositStatusError');

        $responseBody = $this->xmlEscape((string) $result['responseBody']);
        $message = $this->xmlEscape((string) $result['message']);
        $environment = $this->xmlEscape((string) $result['environment']);
        $endpoint = $this->xmlEscape((string) $result['endpoint']);
        $httpStatus = isset($result['httpStatus']) && $result['httpStatus'] !== null
            ? (string) ((int) $result['httpStatus'])
            : '-';

        echo '<!doctype html><html><head><meta charset="UTF-8"><title>'
            . $this->xmlEscape(__('plugins.importexport.OMPBookDepositCrossref.depositResultTitle'))
            . '</title></head><body style="font-family: sans-serif; margin: 24px;">';
        echo '<h2>' . $this->xmlEscape(__('plugins.importexport.OMPBookDepositCrossref.depositResultTitle')) . '</h2>';
        echo '<p><strong>' . $this->xmlEscape(__('plugins.importexport.OMPBookDepositCrossref.depositResultStatus')) . ':</strong> ' . $this->xmlEscape($statusLabel) . '</p>';
        echo '<p><strong>' . $this->xmlEscape(__('plugins.importexport.OMPBookDepositCrossref.depositResultMessage')) . ':</strong> ' . $message . '</p>';
        echo '<p><strong>' . $this->xmlEscape(__('plugins.importexport.OMPBookDepositCrossref.depositResultEnvironment')) . ':</strong> ' . $environment . '</p>';
        echo '<p><strong>' . $this->xmlEscape(__('plugins.importexport.OMPBookDepositCrossref.depositResultEndpoint')) . ':</strong> ' . $endpoint . '</p>';
        echo '<p><strong>' . $this->xmlEscape(__('plugins.importexport.OMPBookDepositCrossref.depositResultHttpStatus')) . ':</strong> ' . $this->xmlEscape($httpStatus) . '</p>';
        echo '<h3>' . $this->xmlEscape(__('plugins.importexport.OMPBookDepositCrossref.depositResultResponse')) . '</h3>';
        echo '<pre style="padding: 12px; border: 1px solid #ccc; background: #f8f8f8; white-space: pre-wrap;">' . $responseBody . '</pre>';
        echo '</body></html>';
        exit;
    }

    /**
     * Escape text for XML nodes.
     */
    protected function xmlEscape($value)
    {
        return htmlspecialchars((string) $value, ENT_COMPAT | ENT_XML1, 'UTF-8');
    }

    /**
     * Normalize locale values to a Crossref-compatible language code (2 letters when possible).
     */
    protected function getLanguageCode($locale)
    {
        $locale = (string) $locale;
        if ($locale === '') {
            return 'en';
        }

        $locale = str_replace('-', '_', $locale);
        $parts = explode('_', $locale);
        $language = strtolower($parts[0]);
        return $language ?: 'en';
    }

    /**
     * Build a contributors block using author data from OMP.
     * @param string $role  Crossref contributor_role value ("author" or "editor")
     */
    protected function buildContributorsXml($authors, $indent = '        ', $role = 'author')
    {
        if (empty($authors)) {
            return '';
        }

        $xml = '';
        $contributorsXml = '';
        $sequence = 'first';

        foreach ($authors as $author) {
            $givenName = trim((string) $author->getLocalizedGivenName());
            $surname = trim((string) $author->getLocalizedFamilyName());
            if ($givenName === '' && $surname === '') {
                continue;
            }

            $contributorsXml .= "{$indent}  <person_name sequence=\"{$sequence}\" contributor_role=\"{$role}\">\n";
            if ($givenName !== '') {
                $contributorsXml .= "{$indent}    <given_name>" . $this->xmlEscape($givenName) . "</given_name>\n";
            }
            if ($surname !== '') {
                $contributorsXml .= "{$indent}    <surname>" . $this->xmlEscape($surname) . "</surname>\n";
            }

            $affiliation = method_exists($author, 'getLocalizedAffiliation') ? trim((string) $author->getLocalizedAffiliation()) : '';
            if ($affiliation !== '') {
                $contributorsXml .= "{$indent}    <affiliations>\n";
                $contributorsXml .= "{$indent}      <institution>\n";
                $contributorsXml .= "{$indent}        <institution_name>" . $this->xmlEscape($affiliation) . "</institution_name>\n";
                $contributorsXml .= "{$indent}      </institution>\n";
                $contributorsXml .= "{$indent}    </affiliations>\n";
            }

            $orcid = method_exists($author, 'getOrcid') ? trim((string) $author->getOrcid()) : '';
            if ($orcid !== '') {
                $contributorsXml .= "{$indent}    <ORCID>" . $this->xmlEscape($orcid) . "</ORCID>\n";
            }

            $contributorsXml .= "{$indent}  </person_name>\n";
            $sequence = 'additional';
        }

        if ($contributorsXml === '') {
            return '';
        }

        $xml .= "{$indent}<contributors>\n";
        $xml .= $contributorsXml;
        $xml .= "{$indent}</contributors>\n";
        return $xml;
    }

    /**
     * Build Crossref-compatible JATS abstract XML from plain or HTML text.
     * @param string $locale  Locale string (e.g. "es_EC") for the xml:lang attribute
     */
    protected function buildJatsAbstractXml($abstract, $indent = '        ', $locale = '')
    {
        $abstract = trim((string) $abstract);
        if ($abstract === '') {
            return '';
        }

        $plainTextAbstract = trim(htmlspecialchars_decode(strip_tags($abstract)));
        if ($plainTextAbstract === '') {
            return '';
        }

        $langAttr = '';
        $lang = $this->getLanguageCode($locale);
        if ($lang !== '') {
            $langAttr = ' xml:lang="' . $this->xmlEscape($lang) . '"';
        }

        return "{$indent}<jats:abstract{$langAttr}><jats:p>" . $this->xmlEscape($plainTextAbstract) . "</jats:p></jats:abstract>\n";
    }

    /**
     * Build publication_date with year and optional month/day.
     */
    protected function buildPublicationDateXml($dateString, $indent = '        ', $mediaType = null)
    {
        $dateString = trim((string) $dateString);
        if ($dateString === '') {
            return '';
        }

        $timestamp = strtotime($dateString);
        if ($timestamp === false) {
            return '';
        }

        $xml = '';
        $mediaTypeAttr = $mediaType ? ' media_type="' . $this->xmlEscape($mediaType) . '"' : '';
        $xml .= "{$indent}<publication_date{$mediaTypeAttr}>\n";
        $month = date('m', $timestamp);
        $day = date('d', $timestamp);
        if ($month !== '00') {
            $xml .= "{$indent}  <month>{$month}</month>\n";
        }
        if ($day !== '00') {
            $xml .= "{$indent}  <day>{$day}</day>\n";
        }
        $xml .= "{$indent}  <year>" . date('Y', $timestamp) . "</year>\n";

        $xml .= "{$indent}</publication_date>\n";
        return $xml;
    }

    /**
     * Build pages XML (first_page/last_page) from chapter pages text.
     */
    protected function buildPagesXml($pages, $indent = '        ')
    {
        $pages = trim((string) $pages);
        if ($pages === '') {
            return '';
        }

        $firstSegment = trim(explode(',', $pages)[0]);
        if ($firstSegment === '') {
            return '';
        }

        // Remove common prefixes like "pp." or "p." when present.
        $firstSegment = preg_replace('/^[[:alpha:]]+[:.]?\s*/u', '', $firstSegment);
        $firstPage = '';
        $lastPage = '';

        if (preg_match('/^([[:alnum:]]+)\s*[-–]{1,2}\s*([[:alnum:]]+)$/u', $firstSegment, $matches)) {
            $firstPage = $matches[1];
            $lastPage = $matches[2];
        } elseif (preg_match('/^([[:alnum:]]+)$/u', $firstSegment, $matches)) {
            $firstPage = $matches[1];
        }

        if ($firstPage === '') {
            return '';
        }

        $xml = "{$indent}<pages>\n";
        $xml .= "{$indent}  <first_page>" . $this->xmlEscape($firstPage) . "</first_page>\n";
        if ($lastPage !== '') {
            $xml .= "{$indent}  <last_page>" . $this->xmlEscape($lastPage) . "</last_page>\n";
        }
        $xml .= "{$indent}</pages>\n";
        return $xml;
    }

    /**
     * Collect ISBN values from publication format identification codes.
     */
    protected function collectIsbnsFromPublicationFormats($publication, $context)
    {
        /** @var \APP\publicationFormat\PublicationFormatDAO $publicationFormatDao */
        $publicationFormatDao = \PKP\db\DAORegistry::getDAO('PublicationFormatDAO');
        $publicationFormats = $publicationFormatDao->getByPublicationId($publication->getId(), $context ? $context->getId() : null);

        $isbns = [];
        foreach ($publicationFormats as $publicationFormat) {
            $mediaType = $publicationFormat->getPhysicalFormat() ? 'print' : 'electronic';
            $identificationCodesResult = $publicationFormat->getIdentificationCodes();
            $identificationCodes = method_exists($identificationCodesResult, 'toArray')
                ? $identificationCodesResult->toArray()
                : (is_array($identificationCodesResult) ? $identificationCodesResult : []);
            foreach ($identificationCodes as $identificationCode) {
                if (!is_object($identificationCode) || !method_exists($identificationCode, 'getValue') || !method_exists($identificationCode, 'getCode')) {
                    continue;
                }

                $value = trim((string) $identificationCode->getValue());
                if ($value === '') {
                    continue;
                }

                $onixCode = trim((string) $identificationCode->getCode());
                $onixName = strtolower(trim((string) $identificationCode->getNameForONIXCode()));
                $isIsbn = strpos($onixName, 'isbn') !== false || in_array($onixCode, ['02', '15'], true);
                if (!$isIsbn) {
                    continue;
                }

                $dedupeKey = $value . '|' . $mediaType;
                if (!isset($isbns[$dedupeKey])) {
                    $isbns[$dedupeKey] = [
                        'value' => $value,
                        'mediaType' => $mediaType,
                    ];
                }
            }
        }

        return array_values($isbns);
    }

    /**
     * Build citation_list XML for a publication from DB-backed citations.
     */
    protected function buildCitationListXmlForPublication($publication, $indent = '        ')
    {
        $citationTexts = [];

        /** @var \PKP\citation\CitationDAO $citationDao */
        $citationDao = \PKP\db\DAORegistry::getDAO('CitationDAO');
        if ($citationDao) {
            $parsedCitations = $citationDao->getByPublicationId((int) $publication->getId())->toArray();
            foreach ($parsedCitations as $citation) {
                $raw = trim((string) $citation->getRawCitation());
                if ($raw !== '') {
                    $citationTexts[] = $raw;
                }
            }
        }

        // Fallback to citationsRaw when parsed citations table is empty.
        if (empty($citationTexts)) {
            $rawCitations = trim((string) $publication->getData('citationsRaw'));
            if ($rawCitations !== '') {
                $citationTexts = preg_split('/(?:\r\n|\r|\n)/', $rawCitations, -1, PREG_SPLIT_NO_EMPTY);
            }
        }

        if (empty($citationTexts)) {
            return '';
        }

        $xml = "{$indent}<citation_list>\n";
        $index = 1;
        foreach ($citationTexts as $citationText) {
            $citationText = trim((string) $citationText);
            if ($citationText === '') {
                continue;
            }

            $key = 'ref' . $index;
            $xml .= "{$indent}  <citation key=\"" . $this->xmlEscape($key) . "\">\n";
            $xml .= "{$indent}    <unstructured_citation>" . $this->xmlEscape($citationText) . "</unstructured_citation>\n";

            // Add DOI when one is present in the citation text.
            $citationDoi = $this->extractDoiFromText($citationText);
            if ($citationDoi !== '') {
                $xml .= "{$indent}    <doi>" . $this->xmlEscape($citationDoi) . "</doi>\n";
            }

            $xml .= "{$indent}  </citation>\n";
            $index++;
        }
        $xml .= "{$indent}</citation_list>\n";

        return $xml;
    }

    /**
     * Try to extract a DOI string from an arbitrary citation text.
     */
    protected function extractDoiFromText($text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        if (preg_match('/10\.\d{4,9}\/[\-._;()\/:A-Z0-9]+/i', $text, $matches)) {
            return rtrim($matches[0], '.,; ');
        }

        return '';
    }

    /**
     * Execute import/export tasks using the command-line interface.
     */
    public function executeCLI($scriptName, &$args)
    {
        die("Esta función de CLI aún no está implementada.\n");
    }

    /**
     * Display the command-line usage information
     */
    public function usage($scriptName)
    {
        echo "Usage: php tools/importExport.php OMPBookDepositCrossrefPlugin [argumentos...]\n";
    }
}
