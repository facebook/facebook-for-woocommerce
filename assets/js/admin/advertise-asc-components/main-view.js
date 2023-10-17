import CampaignSetupView from './campaign-management-view';
import { Layout } from 'antd';

const { Content } = Layout;
const MainView = (props) => {
  return (
    <Layout>
      <Content style={{ padding: '0 50px' }}>
        <CampaignSetupView campaignType={props.props.campaignType} campaignDetails={props.props.campaignDetails} isUpdate={props.props.isUpdate} onFinish={props.onFinish} />
      </Content>
    </Layout>
  );
};

export default MainView;