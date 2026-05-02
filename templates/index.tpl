{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{$pageTitle|escape}
	</h1>

	<script type="text/javascript">
		$(function() {
			$('#importExportTabs').pkpHandler('$.pkp.controllers.TabHandler');
			$('#importExportTabs').tabs('option', 'cache', true);
		});
	</script>
	<div id="importExportTabs" class="pkp_controllers_tab">
		<ul>
			<li><a href="#exportSubmissions-tab">{translate key="plugins.importexport.OMPBookDepositCrossref.exportTab"}</a></li>
			<li><a href="#depositSubmissions-tab">{translate key="plugins.importexport.OMPBookDepositCrossref.depositTab"}</a></li>
			<li><a href="#config-tab">{translate key="plugins.importexport.OMPBookDepositCrossref.configTab"}</a></li>
		</ul>

		{{-- Tab 1: Exportar XML --}}
		<div id="exportSubmissions-tab">
			<form id="exportXmlForm" class="pkp_form" action="{plugin_url path="exportSubmissionsBounce"}" method="post">
				{csrf}
				{fbvFormArea id="exportForm"}
					<p class="pkp_help">
						{translate key="plugins.importexport.OMPBookDepositCrossref.schemaNotice"}
					</p>
					<submissions-list-panel
						v-bind="components.submissions"
						@set="set"
					>
						<template v-slot:item="{ldelim}item{rdelim}">
							<div class="listPanel__itemSummary">
								<label>
									<input
										type="checkbox"
										name="selectedSubmissions[]"
										:value="item.id"
										v-model="selectedSubmissions"
									/>
									<span class="listPanel__itemSubTitle">
										{{ item.id }} - {{ item.publications ? (item.publications.find(p => p.id === item.currentPublicationId) || item.publications[0]).title[Object.keys((item.publications.find(p => p.id === item.currentPublicationId) || item.publications[0]).title)[0]] : __('common.untitled') }}
									</span>
								</label>
								<pkp-button element="a" :href="item.urlWorkflow" style="margin-left: auto;">
									{{ __('common.view') }}
								</pkp-button>
							</div>
						</template>
					</submissions-list-panel>
					<br><br>
					{fbvFormSection}
						<button class="pkp_button" type="submit">
							{translate key="plugins.importexport.OMPBookDepositCrossref.exportButton"}
						</button>
					{/fbvFormSection}
				{/fbvFormArea}
			</form>
		</div>

		{{-- Tab 2: Depositar en Crossref --}}
		<div id="depositSubmissions-tab">
			<form id="depositCrossrefForm" class="pkp_form" action="{plugin_url path="depositSubmissionsBounce"}" method="post">
				{csrf}
				{fbvFormArea id="depositForm"}
					<p class="pkp_help">
						{translate key="plugins.importexport.OMPBookDepositCrossref.depositNotice"}
					</p>
					<submissions-list-panel
						v-bind="components.submissions"
						@set="set"
					>
						<template v-slot:item="{ldelim}item{rdelim}">
							<div class="listPanel__itemSummary">
								<label>
									<input
										type="checkbox"
										name="selectedSubmissions[]"
										:value="item.id"
										v-model="selectedSubmissions"
									/>
									<span class="listPanel__itemSubTitle">
										{{ item.id }} - {{ item.publications ? (item.publications.find(p => p.id === item.currentPublicationId) || item.publications[0]).title[Object.keys((item.publications.find(p => p.id === item.currentPublicationId) || item.publications[0]).title)[0]] : __('common.untitled') }}
									</span>
								</label>
								<pkp-button element="a" :href="item.urlWorkflow" style="margin-left: auto;">
									{{ __('common.view') }}
								</pkp-button>
							</div>
						</template>
					</submissions-list-panel>
					<br><br>
					{fbvFormSection title="plugins.importexport.OMPBookDepositCrossref.depositConfigTitle"}
						<label for="crossrefEnvironment" style="display:block; margin-bottom: 8px;">
							{translate key="plugins.importexport.OMPBookDepositCrossref.depositEnvironment"}
						</label>
						<select id="crossrefEnvironment" name="crossrefEnvironment" class="selectMenu" style="margin-bottom: 16px; min-width: 220px;">
							<option value="test"{if $savedEnvironment === 'test'} selected{/if}>{translate key="plugins.importexport.OMPBookDepositCrossref.depositEnvironmentTest"}</option>
							<option value="live"{if $savedEnvironment === 'live'} selected{/if}>{translate key="plugins.importexport.OMPBookDepositCrossref.depositEnvironmentLive"}</option>
						</select>

						<label for="crossrefLoginId" style="display:block; margin-bottom: 8px;">
							{translate key="plugins.importexport.OMPBookDepositCrossref.depositLoginId"}
						</label>
						<input id="crossrefLoginId" type="text" name="crossrefLoginId" value="{$savedLoginId|escape}" style="margin-bottom: 16px; min-width: 320px;" />

						<label for="crossrefLoginPasswd" style="display:block; margin-bottom: 8px;">
							{translate key="plugins.importexport.OMPBookDepositCrossref.depositLoginPasswd"}
						</label>
						<input id="crossrefLoginPasswd" type="password" name="crossrefLoginPasswd" placeholder="{if $savedLoginPasswd}{translate key="plugins.importexport.OMPBookDepositCrossref.configPasswordSaved"}{/if}" style="margin-bottom: 16px; min-width: 320px;" />
					{/fbvFormSection}

					{fbvFormSection}
						<button class="pkp_button" type="submit">
							{translate key="plugins.importexport.OMPBookDepositCrossref.depositButton"}
						</button>
					{/fbvFormSection}
				{/fbvFormArea}
			</form>
		</div>

		{{-- Tab 3: Configuración --}}
		<div id="config-tab">
			{if $settingsSaved}
				<div class="pkp_controllers_notification" style="margin-bottom: 16px;">
					{translate key="plugins.importexport.OMPBookDepositCrossref.configSaved"}
				</div>
			{/if}
			<form id="configForm" class="pkp_form" action="{plugin_url path="saveSettingsBounce"}" method="post">
				{csrf}
				{fbvFormArea id="configFormArea"}
					<p class="pkp_help">
						{translate key="plugins.importexport.OMPBookDepositCrossref.configNotice"}
					</p>
					{fbvFormSection title="plugins.importexport.OMPBookDepositCrossref.depositConfigTitle"}
						<label for="configEnvironment" style="display:block; margin-bottom: 8px;">
							{translate key="plugins.importexport.OMPBookDepositCrossref.depositEnvironment"}
						</label>
						<select id="configEnvironment" name="crossrefEnvironment" class="selectMenu" style="margin-bottom: 16px; min-width: 220px;">
							<option value="test"{if $savedEnvironment === 'test'} selected{/if}>{translate key="plugins.importexport.OMPBookDepositCrossref.depositEnvironmentTest"}</option>
							<option value="live"{if $savedEnvironment === 'live'} selected{/if}>{translate key="plugins.importexport.OMPBookDepositCrossref.depositEnvironmentLive"}</option>
						</select>

						<label for="configLoginId" style="display:block; margin-bottom: 8px;">
							{translate key="plugins.importexport.OMPBookDepositCrossref.depositLoginId"}
						</label>
						<input id="configLoginId" type="text" name="crossrefLoginId" value="{$savedLoginId|escape}" style="margin-bottom: 16px; min-width: 320px;" />

						<label for="configLoginPasswd" style="display:block; margin-bottom: 8px;">
							{translate key="plugins.importexport.OMPBookDepositCrossref.depositLoginPasswd"}
						</label>
						<input id="configLoginPasswd" type="password" name="crossrefLoginPasswd" placeholder="{if $savedLoginPasswd}{translate key="plugins.importexport.OMPBookDepositCrossref.configPasswordSaved"}{else}{translate key="plugins.importexport.OMPBookDepositCrossref.configPasswordEmpty"}{/if}" style="margin-bottom: 16px; min-width: 320px;" />
						<p class="pkp_help">
							{translate key="plugins.importexport.OMPBookDepositCrossref.configPasswordHint"}
						</p>
					{/fbvFormSection}

					{fbvFormSection}
						<button class="pkp_button" type="submit">
							{translate key="plugins.importexport.OMPBookDepositCrossref.configSaveButton"}
						</button>
					{/fbvFormSection}
				{/fbvFormArea}
			</form>
		</div>
	</div>
{/block}
