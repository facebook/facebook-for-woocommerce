import { useState } from '@wordpress/element';
import { Button, Form, Modal, Space, Spin, Steps } from 'antd';
import CampaignEditView from './campaign-edit-view';
import CampaignPreviewView from './campaign-preview-view'

const CampaignSetupView = (props) => {

    const defaultAdMessage = props.campaignType == 'retargeting' ? 'These great products are still waiting for you!' : 'Check out these great products!';

    var minDailyBudget = props.campaignDetails["minDailyBudget"];
    const [adMessage, setAdMessage] = useState(props.campaignDetails["adMessage"] ?? defaultAdMessage);
    const [dailyBudget, setDailyBudget] = useState(props.campaignDetails["dailyBudget"] ?? minDailyBudget);
    var [countryList, setCountryList] = useState(props.campaignDetails["selectedCountries"] ?? ['US']);
    var [currentState, setCurrentState] = useState(props.campaignDetails["status"] ?? true);

    var currency = props.campaignDetails["currency"];

    const goToEditCampaignPage = () => {
        setActiveKey(0);
        setHeaders(getHeaders(0));
        setCurrent(0);
    };

    const goToPreviewPage = () => {
        setActiveKey(1);
        setHeaders(getHeaders(1));
        setCurrent(1);

        props.firstLoad = false;
    };

    const publishChanges = () => {
        
        const requestData = JSON.stringify({
            campaignType: props.campaignType,
            dailyBudget: String(dailyBudget),
            isUpdate: String(props.isUpdate),
            adMessage: adMessage,
            countryList: countryList,
            status: String(currentState),
        });

        setPublishing(true);
        fetch(facebook_for_woocommerce_settings_advertise_asc.ajax_url + '?action=wc_facebook_advertise_asc_publish_changes', {
            method: 'post',
            headers: { 'Content-Type': 'application/json' },
            body: requestData
        })
            .then((response) => response.json())
            .then((data) => {
                if ( ! data['success'] ) {
                    Modal.error({
                        title: 'Publish changes failed',
                        content: data['data'],
                      });
                } else {
                    props.onFinish();
                }
                setPublishing(false);
            })
            .catch((err) => {
                Modal.error({
                title: 'Publish changes failed',
                content: err.mes,
              });
            });
    };

    const onFinishFailed = (errorInfo) => { 
        Modal.error({
            title: 'Submit form failed',
            content: errorInfo,
          });
    };

    const getHeaders = (activeTabIndex) => {
        const otherTabIndex = 1 - activeTabIndex;
        const activeTabStatus = 'process';
        const otherTabStatus = activeTabIndex > otherTabIndex ? 'finished' : 'wait';
        const tabTitles = {
            0: (props.isUpdate ? 'Edit Campaign' : 'Create Campaign'),
            1: 'Preview'
        };

        const result = [];
        result[activeTabIndex] = {
            status: activeTabStatus,
            title: tabTitles[activeTabIndex]
        };
        result[otherTabIndex] = {
            status: otherTabStatus,
            title: tabTitles[otherTabIndex]
        };

        return result;
    }

    const [current, setCurrent] = useState(0);
    const [activeKey, setActiveKey] = React.useState(0);
    const [headers, setHeaders] = useState(getHeaders(0));
    const [publishing, setPublishing] = useState(false);

    return (
        <Space direction='vertical'>
            <Steps
                style={{ width: '600px' }}
                type='navigation'
                current={current}
                onChange={() => { }}
                className="site-navigation-steps"
                items={headers}
            />
            { activeKey == 0 ? (
                <Space direction='vertical'>
                    <Form onFinish={goToPreviewPage} onFinishFailed={onFinishFailed}>
                        <CampaignEditView showCountry={props.campaignType != 'retargeting'} currency={currency} campaignType={props.campaignType} minDailyBudget={minDailyBudget} selectedCountries={countryList} currentStatus={currentState} onCountryListChange={(e) => { setCountryList(e); }} onStatusChange={(e) => { setCurrentState(e); }} message={adMessage} onMessageChange={(msg) => { setAdMessage(msg); }} dailyBudget={dailyBudget} onDailyBudgetChange={(budget) => { setDailyBudget(budget); }} />
                        <div className='navigation-footer-container'>
                            <Form.Item className='navigation-footer-button fit-to-left' >
                                <Button  onClick={() => props.onFinish()}>Cancel</Button>
                            </Form.Item>
                            <Form.Item className='navigation-footer-button fit-to-right'>
                                <Button  htmlType='submit'>Next</Button>
                            </Form.Item>
                        </div>
                    </Form>
                </Space>
            ) : (
                <Space direction='vertical'>
                    <CampaignPreviewView message={adMessage} activeKey={activeKey} campaignType={props.campaignType} onSizeChange={()=>{}}/>
                    <div className='navigation-footer-container'>
                        <Button disabled={publishing} className='navigation-footer-button fit-to-left' onClick={() => goToEditCampaignPage()}>Back</Button>
                        <div className='navigation-footer-button fit-to-right'>
                            <Spin spinning={publishing} >
                                <Button onClick={() => publishChanges()}>Publish Changes</Button>
                            </Spin>
                        </div>
                    </div>
                </Space>)}
        </Space>
    );
};

export default CampaignSetupView;