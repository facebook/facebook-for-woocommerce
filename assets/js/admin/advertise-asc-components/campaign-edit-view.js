import { useState } from '@wordpress/element';
import { Card, Col, Form, Input, InputNumber, Row, Select, Space, Switch, Tooltip } from 'antd';
import { DollarOutlined, EditOutlined, GlobalOutlined, PoweroffOutlined, QuestionCircleFilled } from '@ant-design/icons'
import { CountryList } from './eligible-country-list'

const ProspectingViewComponent = (props) => {
    const countryPairs = CountryList.map((c) => {
        return {
            key: Object.keys(c)[0],
            value: Object.values(c)[0]
        }
    });

    const [selectedCountries, setSelectedCountries] = useState(props.selectedCountries);

    const availableOptions = countryPairs.filter(x => { return selectedCountries.indexOf(x) == -1; });
    
    return (
        <>
            <p className='campaign-edit-view-header' style={{ margin: '10px 0 0 0', width: '250px' }}>
                <GlobalOutlined className='campaign-edit-view-header' /> {' '} Country {' '} <Tooltip title="Countries where your campaign will be shown."><QuestionCircleFilled className='campaign-edit-view-header-tooltip' /></Tooltip>
            </p>
            <div className='transparent-background' style={{ width: '250px' }}>
                <Form.Item
                    className='zero-border-element transparent-background'
                    style={{ width: '250px' }}
                    label=''
                    name='n1'
                    initialValue={selectedCountries}
                    rules={[
                        {
                            required: true,
                            message: 'You should select at least one country',
                        }
                    ]}>
                    <Select mode="multiple"
                        maxTagCount={5}
                        onChange={(c) => { 
                            setSelectedCountries(c); 
                            props.onCountryListChange(c);
                            }}
                        style={{ marginTop: 0, width: '100%', maxWidth: '200px', maxHeight: '180px', whiteSpace: 'nowrap', overflow: 'auto' }}
                        options={availableOptions.map((item) => ({
                            value: item['key'],
                            label: item['value']
                        }))} />
                </Form.Item>
            </div>
        </>);
};

const CampaignEditView = (props) => {

    const title = props.campaignType == 'retargeting' ? 'Retargeting Campaign' : 'New Customers Campaign';
    const subtitle = props.campaignType == 'retargeting' ? "Bring back visitors who visited your website and didn't complete their purchase" : "Reach out to potential new buyers for your products";
    const { TextArea } = Input;
    const [message, setMessage] = useState(props.message);
    const [dailyBudget, setDailyBudget] = useState(props.dailyBudget);
    const [status, setStatus] = useState(props.currentStatus);
    const minDailyBudget = parseFloat(props.minDailyBudget);
    
    return (
        <Card>
            <Row>
                <Col>
                    <h1 style={{ fontSize: "23px", fontWeight: 400, margin: 0, padding: "9px 0 4px", lineHeight: "1.3" }}>{title}</h1>
                    <p style={{ marginTop: 0, marginBottom: '20px', color: "#949494", boxSizing: 'border-box' }}>{subtitle}</p>
                </Col>
                <Col></Col>
            </Row>
            <Row>
                <Col>
                </Col>
                <Col>
                    <Space direction='vertical'>
                        <p className='zero-border-element campaign-edit-view-header'>
                            <PoweroffOutlined className='campaign-edit-view-header' /> {' '} Campaign Off/On {' '} <Tooltip title="Do you want your campaign to actively run? Make sure to select 'On'"><QuestionCircleFilled className='campaign-edit-view-header-tooltip' /></Tooltip>
                        </p>
                        <div className='zero-border-element'>
                            <Form.Item 
                                className='zero-border-element'
                                label=''
                                name='n4'
                                >
                                <Switch
                                    checked={status}
                                    className='zero-border-element'
                                    checkedChildren="On"
                                    unCheckedChildren="Off"
                                    onChange={(e) => { setStatus(e); props.onStatusChange(e);}}
                                    />
                            </Form.Item>
                        </div>
                        

                        <p className='campaign-edit-view-header' style={{ margin: '10px 0 0 0' }}>
                            <DollarOutlined className='campaign-edit-view-header' /> {' '} Daily Budget {' '} <Tooltip title="How much would you want to spend on a daily basis?"><QuestionCircleFilled className='campaign-edit-view-header-tooltip' />
                            </Tooltip>
                        </p>
                        <div className='zero-border-element' style={{ width:'200px' }}>
                            <Form.Item
                                className='zero-border-element'
                                label=''
                                name='n2'
                                initialValue={dailyBudget}
                                rules={[
                                    {
                                        required: true,
                                        message: 'Daily budget must be set',
                                    },
                                    {
                                        message: 'Minimum allowed daily budget is ' + props.minDailyBudget + ' ' + props.currency,
                                        validator: (_, value) => {
                                            if (value && (parseFloat(value) < minDailyBudget)) {
                                                return Promise.reject();
                                            } else {
                                                return Promise.resolve();
                                            }
                                        }
                                    }
                                ]}>
                                <InputNumber
                                    style={{
                                        width: 200,
                                        margin: 0
                                    }}
                                    key={1}
                                    step="0.1"
                                    onChange={(e) => { props.onDailyBudgetChange(e); setDailyBudget(e); }}
                                    stringMode
                                    addonAfter={props.currency}
                                />
                            </Form.Item>
                        </div>
                        {props.showCountry ? (
                            <ProspectingViewComponent onCountryListChange={props.onCountryListChange} selectedCountries={props.selectedCountries} />
                        ) : <></>}

                    </Space>
                </Col>
                <Col style={{ marginLeft: "40px" }}>
                    <Space direction='vertical'>
                        <div className='zero-border-element' style={{ display: 'inline-block', margin: '4px 0 0 0' }}>
                            <p className='zero-border-element campaign-edit-view-header' style={{ marginLeft: '10px;' }}>
                                <EditOutlined className='campaign-edit-view-header' /> {' '} Customize your message <Tooltip title="The pitch for selling your products. Choose it wisely!"><QuestionCircleFilled className='campaign-edit-view-header-tooltip' /></Tooltip>
                            </p>
                            <p className='zero-border-element campaign-edit-secondary-header' >{'The carousel will show your products'}</p>
                        </div>
                        <div className='transparent-background campaign-edit-view-thumbnail-container'>
                            <img className='campaign-edit-view-img' src={require('!!url-loader!./../../../images/woo_post1.png').default} />
                            <Form.Item
                                label=''
                                name='n3'
                                initialValue={message}
                                rules={[
                                    {
                                        required: true,
                                        message: 'Ad message must be set.',
                                    }
                                ]}>
                                <TextArea className='campaign-edit-view-messagebox' compact={true} onChange={(e) => { props.onMessageChange(e.target.value); setMessage(e.target.value); }}></TextArea>
                            </Form.Item>
                            <img className='campaign-edit-view-img' style={{ marginRight: 0 }} src={require('!!url-loader!./../../../images/woo_post2.png').default} />
                        </div>
                    </Space>
                </Col>
            </Row>

        </Card >
    );
};

export default CampaignEditView;