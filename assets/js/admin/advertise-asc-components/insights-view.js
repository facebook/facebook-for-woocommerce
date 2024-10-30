import { useState } from '@wordpress/element';
import { Button, Card, CardBody, Icon, Flex, FlexItem, Modal, Spinner, Tooltip } from '@wordpress/components';
import { helpFilled, warning } from '@wordpress/icons';
import { CountryList } from './eligible-country-list'
import CampaignPreviewView from './campaign-preview-view'
import CampaignToggle from './campaign-toggle'
import CountrySelector from './country-selector'

function UpdateState(callback, errorCallback, campaignType, state) {

    const requestData = JSON.stringify({
        campaignType: campaignType,
        status: String(state),
    });

    fetch(facebook_for_woocommerce_settings_advertise_asc.ajax_url + '?action=wc_facebook_update_ad_status', {
        method: 'post',
        headers: { 'Content-Type': 'application/json' },
        body: requestData
    })
        .then((response) => response.json())
        .then((data) => {
            if (!data['success']) {
                errorCallback('Failed to update status', data['data']);
            } else {
                callback();
            }
        })
        .catch((err) => {
            errorCallback('Failed to update status', err.message);
        });
}

const FunnelComponentView = (props) => {

    const sum = props.reach + props.clicks + props.views + props.addToCarts + props.purchases;
    return (
        <table class="bar-chart transparent-background">
            <thead style={{ height: '90%' }}>
                <th><div style={{ marginRight: '20px', width: '50px', height: 200.0 * (props.reach / sum) + 'px' }}></div></th>
                <th><div style={{ marginRight: '20px', width: '50px', height: 200.0 * (props.clicks / sum) + 'px' }}></div></th>
                <th><div style={{ marginRight: '20px', width: '50px', height: 200.0 * (props.views / sum) + 'px' }}></div></th>
                <th><div style={{ marginRight: '20px', width: '50px', height: 200.0 * (props.addToCarts / sum) + 'px' }}></div></th>
                <th><div style={{ marginRight: '20px', width: '50px', height: 200.0 * (props.purchases / sum) + 'px' }}></div></th>
                <th></th>
            </thead>
            <tbody style={{ height: '10%' }}>
                <tr>
                    <td><div class="funnel-table-header" flex-direction='column'><label>Reach</label><label>{props.reach}</label></div></td>
                    <td><div class="funnel-table-header" flex-direction='column'><label>Clicks</label><label>{props.clicks}</label></div></td>
                    <td><div class="funnel-table-header" flex-direction='column'><label>Views</label><label>{props.views}</label></div></td>
                    <td><div class="funnel-table-header" flex-direction='column'><label>Add to cart</label><label>{props.addToCarts}</label></div></td>
                    <td><div class="funnel-table-header" flex-direction='column'><label>Purchase</label><label>{props.purchases}</label></div></td>
                    <td>
                        <Tooltip text="X-through rate, Cost per action"><Icon icon={helpFilled} size={12} style={{ fontSize: '75%', alignContent: 'center' }} className='campaign-edit-view-header-tooltip' /></Tooltip>
                    </td>
                </tr>
            </tbody>
        </table>
    );
}

<Flex direction={['row']} className={"funnel-table-header"}><FlexItem>
</FlexItem></Flex>

const InsightsView = (props) => {

    const allCountries = CountryList.map((c) => { return { key: Object.keys(c)[0], label: Object.values(c)[0] } });
    const selectedCountries = allCountries.filter(c => { return props.countryList.indexOf(c.key) >= 0 });
    const [loading, setLoading] = useState(false);
    const [state, setState] = useState(props.status);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [width, setWidth] = useState(0);
    const [height, setHeight] = useState(0);
    const [isErrorDisplayed, setIsErrorDisplayed] = useState(false);
    const [errorMessage, setErrorMessage] = useState({ title: '', content: '' });

    const closeErrorModal = () => setIsErrorDisplayed(false);

    const openErrorModal = (title, content) => {
        setErrorMessage({
            title: title,
            content: content
        });
        setIsErrorDisplayed(true);
    };

    const onEditButtonClicked = () => {
        window.editCampaignButtonClicked(props.campaignType);
    };

    const onStatusChange = (newValue) => {
        setState(newValue);
        setLoading(true);
        UpdateState(() => { setLoading(false) }, openErrorModal, props.campaignType, newValue);
    };

    const showAdPreview = () => {
        setIsModalOpen(true);
    };

    return (
        <>
            <Card>
                <CardBody>
                    <Flex direction={['column']}>
                        <FlexItem>
                            <Flex justify="" direction={['row']} gap={3}>
                                <FlexItem>
                                    <table style={{ textAlign: 'center', verticalAlign: 'top' }}>
                                        <thead >
                                            <th><p className="insights-header">Status</p></th>
                                            <th><p className="insights-header">Spend</p></th>
                                            <th><p className="insights-header">Daily Budget</p></th>
                                            {props.campaignType == 'retargeting' ? (<></>) : (<th><p className="insights-header">Country</p></th>)}
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    {loading ? <Spinner /> : <CampaignToggle
                                                        className='zero-border-element'
                                                        disabled={loading}
                                                        checked={state}
                                                        onChange={(e) => { onStatusChange(e); }}
                                                    />}
                                                </td>
                                                <td><p className='zero-border-element'>{props.spend} {props.currency}</p></td>
                                                <td><p className='zero-border-element'>{props.dailyBudget} {props.currency}</p></td>
                                                {props.campaignType == 'retargeting' ? (<></>) : (
                                                    <td>
                                                        <CountrySelector
                                                            value={selectedCountries.map((item) => item['key'])}
                                                            options={selectedCountries}
                                                        />
                                                    </td>)}
                                            </tr>
                                        </tbody>
                                    </table>
                                </FlexItem>
                                {props.reach > 0 ? (
                                    <FlexItem>
                                        <FunnelComponentView reach={props.reach} clicks={props.clicks} views={props.views} addToCarts={props.addToCarts} purchases={props.purchases} />
                                    </FlexItem>
                                ) : (
                                    <></>
                                )}

                            </Flex>
                        </FlexItem>
                        <FlexItem>
                            <Button variant="secondary" onClick={showAdPreview}>Show Ad</Button>
                            <Button variant="secondary" onClick={onEditButtonClicked} style={{ marginLeft: "10px" }}>Edit</Button>
                        </FlexItem>
                    </Flex>
                </CardBody>
            </Card >
            {isModalOpen && (<Modal title="Ad Preview" onRequestClose={() => { setIsModalOpen(false); }} size={"large"}>
                <CampaignPreviewView preview={true} campaignType={props.campaignType} onSizeChange={(w, h) => {
                    setWidth(w);
                    setHeight(h);
                }} />
            </Modal>)}
            {isErrorDisplayed && (
                <Modal icon={<Icon icon={warning} />} title={errorMessage.title} onRequestClose={closeErrorModal} size={"small"}>
                    <p>{errorMessage.content}</p>
                    <Button variant="primary" onClick={closeErrorModal}>OK</Button>
                </Modal>
            )}
        </>
    );
}

export default InsightsView;