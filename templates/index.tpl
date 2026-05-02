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
		</ul>
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
	</div>
{/block}
