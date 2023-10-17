/**
 * Copyright ( c ) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package
 */

jQuery(document).ready(function () {
	const $ = jQuery;
	const viewItemsIdPrefix = 'woocommerce-facebook-settings-advertise-asc-';
	const ascTypeRetargeting = 'retargeting';
	const ascTypeNewBuyers = 'new-buyers';

	function loadCampaignSetupUi(campaignType, isUpdate, campaignDetails) {
		$('#overlay-view-ui').removeClass('hidden_view');
		$('#base-view-row').addClass('hidden_view');

		window.campaignCreationUILoader('asc-overlay-root', {
			isUpdate,
			campaignType,
			campaignDetails
		}, function () {
			window.location.reload();
		});
	}

	function createInsights(campaignType) {
		const rootElementId = viewItemsIdPrefix + 'insights-placeholder-root-' + campaignType;

		const element = document.getElementById(rootElementId);
		if (element) {
			const countryList = $("#" + viewItemsIdPrefix + "targeting-" + campaignType).val();
			const props = {
				spend: $("#" + viewItemsIdPrefix + "ad-insights-spend-" + campaignType).val(),
				reach: $("#" + viewItemsIdPrefix + "ad-insights-reach-" + campaignType).val(),
				clicks: $("#" + viewItemsIdPrefix + "ad-insights-clicks-" + campaignType).val(),
				views: $("#" + viewItemsIdPrefix + "ad-insights-views-" + campaignType).val(),
				addToCarts: $("#" + viewItemsIdPrefix + "ad-insights-carts-" + campaignType).val(),
				purchases: $("#" + viewItemsIdPrefix + "ad-insights-purchases-" + campaignType).val(),
				dailyBudget: $("#" + viewItemsIdPrefix + "ad-daily-budget-" + campaignType).val(),
				countryList: countryList?.split(',') ?? [],
				currency: $("#" + viewItemsIdPrefix + "currency-" + campaignType).val(),
				status: $("#" + viewItemsIdPrefix + "ad-status-" + campaignType).val(),
				campaignType: campaignType,
			};
			window.insightsUILoader(rootElementId, props);
		}
	}

	$('#new-buyers-create-campaign-img').prop('src', require('!!url-loader!./../../images/prospecting.png').default);
	$('#retargeting-create-campaign-img').prop('src', require('!!url-loader!./../../images/retargeting.png').default);

	function addCampaignSetupHooks() {

		const campaignCreationHook = (campaignType) => {
			$('#' + campaignType + '-create-campaign-btn').click(function () {
				loadCampaignSetupUi(campaignType, false, {
					minDailyBudget: $("#" + viewItemsIdPrefix + "min-ad-daily-budget-" + campaignType).val(),
					currency: $("#" + viewItemsIdPrefix + "currency-" + campaignType).val(),
					dailyBudget: $("#" + viewItemsIdPrefix + "ad-daily-budget-" + campaignType).val(),
				});
			});
			$('#' + campaignType + '-create-campaign-btn').prop('disabled', false);
		};


		window.editCampaignButtonClicked = (campaignType) => {
			const selectedCountries = $("#" + viewItemsIdPrefix + "targeting-" + campaignType).val();

			loadCampaignSetupUi(campaignType, true, {
				adMessage: $("#" + viewItemsIdPrefix + "ad-message-" + campaignType).val(),
				dailyBudget: $("#" + viewItemsIdPrefix + "ad-daily-budget-" + campaignType).val(),
				minDailyBudget: $("#" + viewItemsIdPrefix + "min-ad-daily-budget-" + campaignType).val(),
				selectedCountries: selectedCountries.split(','),
				currency: $("#" + viewItemsIdPrefix + "currency-" + campaignType).val(),
				status: $("#" + viewItemsIdPrefix + "ad-status-" + campaignType).val(),
			});
			
		};

		campaignCreationHook(ascTypeRetargeting);
		campaignCreationHook(ascTypeNewBuyers);
	}


	$('.woocommerce-help-tip').tipTip({
		attribute: 'data-tip',
		fadeIn: 50,
		fadeOut: 50,
		delay: 200,
	});

	createInsights(ascTypeNewBuyers);
	createInsights(ascTypeRetargeting);
	addCampaignSetupHooks();
});