import { createRoot, createElement } from '@wordpress/element';
import MainView from './advertise-asc-components/main-view';
import InsightsView from './advertise-asc-components/insights-view';

function campaignCreationUILoader(rootElementId, props, reset) {
  const element = document.getElementById(rootElementId);
  if (element) {
    const root = createRoot(element);
    root.render( 
      createElement(MainView, {props:props, onFinish: reset})
    );
  }
}

function insightsUILoader(rootElementId, props) {
  const element = document.getElementById(rootElementId);
  if (element) {
    const root = createRoot(element);    
    root.render(
      createElement(InsightsView, {
        spend:props.spend,
        dailyBudget:props.dailyBudget,
        reach:props.reach,
        clicks:props.clicks,
        views:props.views,
        addToCarts:props.addToCarts,
        purchases:props.purchases,
        countryList:props.countryList,
        status:props.status,
        currency:props.currency,
        campaignType:props.campaignType
      })
    );
  }
}

window.campaignCreationUILoader = campaignCreationUILoader;
window.insightsUILoader = insightsUILoader;
