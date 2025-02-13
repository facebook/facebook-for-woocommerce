import CampaignSetupView from './campaign-management-view';
import { Panel, PanelBody, PanelRow } from '@wordpress/components';

const MainView = (props) => {
  return (
    <Panel>
      <PanelBody>
        <PanelRow>
          <CampaignSetupView
            campaignType={props.props.campaignType}
            campaignDetails={props.props.campaignDetails}
            isUpdate={props.props.isUpdate}
            onFinish={props.onFinish} />
        </PanelRow>
      </PanelBody>
    </Panel>
  );
};

export default MainView;