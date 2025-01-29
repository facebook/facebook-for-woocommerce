import { useState } from '@wordpress/element';
import { Button, ButtonGroup, Card, CardBody, CardHeader, CardFooter, Icon, Modal, TabPanel } from '@wordpress/components';
import { warning } from '@wordpress/icons';
import CampaignEditView from './campaign-edit-view';
import CampaignPreviewView from './campaign-preview-view'


const CampaignSetupView = (props) => {

    const RETARGETING_CAMPAIGN_TYPE = 'retargeting';
    const defaultAdMessage = props.campaignType == RETARGETING_CAMPAIGN_TYPE ? 'These great products are still waiting for you!' : 'Check out these great products!';
    const minDailyBudget = props.campaignDetails["minDailyBudget"] ?? 0;
    const currency = props.campaignDetails["currency"] ?? 'USD';
    const [adMessage, setAdMessage] = useState(props.campaignDetails["adMessage"] ?? defaultAdMessage);
    const [dailyBudget, setDailyBudget] = useState(props.campaignDetails["dailyBudget"] ?? minDailyBudget);
    const [countryList, setCountryList] = useState(props.campaignDetails["selectedCountries"] ?? ['US']);
    const [currentState, setCurrentState] = useState(props.campaignDetails["status"] ?? true);
    const [errorMessage, setErrorMessage] = useState("");
    const [invalidInputMessage, setInvalidInputMessage] = useState([]);

    const validateInput = () => {
        var errors = [];

        if (dailyBudget == 0) {
            errors.push("Daily budget must be set");

        } else if (parseFloat(dailyBudget) < parseFloat(minDailyBudget)) {
            errors.push("Minimum allowed daily budget is " + minDailyBudget + " " + currency);
        }

        if (countryList.length == 0) {
            errors.push("You should select at least one country");
        }

        if (adMessage == "") {
            errors.push("Ad message must be set.");
        }

        setInvalidInputMessage(errors);

        return errors.length == 0;
    };

    const openErrorModal = (title) => {
        setErrorMessage(title);
    };
    const closeErrorModal = () => setErrorMessage("");

    const goToEditCampaignPage = () => {
        setActiveKey(0);
        setHeaders(getHeaders(0));
        setCurrent(0);
    };

    const goToPreviewPage = () => {
        if (!validateInput()) {
            return false;
        }

        setActiveKey(1);
        setHeaders(getHeaders(1));
        setCurrent(1);
        props.firstLoad = false;
    };

    const publishChanges = () => {
        if (!validateInput()) {
            return false;
        }

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
                if (!data['success']) {
                    openErrorModal(data['data']);
                } else {
                    props.onFinish();
                }
                setPublishing(false);
            })
            .catch((err) => {
                openErrorModal(err.mes);
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

        var result = [];
        result[activeTabIndex] = {
            name: activeTabIndex,
            disabled: false,
            status: activeTabStatus,
            title: tabTitles[activeTabIndex]
        };
        result[otherTabIndex] = {
            name: otherTabIndex,
            disabled: true,
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
        <>
            <Card>
                <CardHeader>
                    <TabPanel
                        orientation={"horizontal"}
                        tabs={headers}                        
                    />
                </CardHeader>
                <CardBody>
                    {activeKey == 0 ? (
                        <div direction='vertical'>
                            <CampaignEditView
                                campaignType={props.campaignType}
                                currency={currency}
                                currentStatus={currentState}
                                dailyBudget={dailyBudget}
                                invalidInputMessage={invalidInputMessage}
                                isRetargeting={props.campaignType == RETARGETING_CAMPAIGN_TYPE}
                                message={adMessage}
                                minDailyBudget={minDailyBudget}
                                selectedCountries={countryList}
                                onCountryListChange={(e) => setCountryList(e)}
                                onDailyBudgetChange={(budget) => setDailyBudget(budget)}
                                onMessageChange={(msg) => setAdMessage(msg)}
                                onStatusChange={(status) => setCurrentState(status)}
                            />
                        </div>
                    ) : (
                        <div direction='vertical'>
                            <CampaignPreviewView
                                activeKey={activeKey}
                                campaignType={props.campaignType}
                                message={adMessage}
                            />
                        </div>
                    )}
                </CardBody>
                <CardFooter>
                    {
                        activeKey == 0
                            ? (<div className='navigation-footer-container'>
                                <Button
                                    className='navigation-footer-button fit-to-left'
                                    onClick={() => props.onFinish()}
                                    variant="secondary">Cancel
                                </Button>
                                <ButtonGroup className='navigation-footer-button fit-to-right'>
                                    <Button
                                        onClick={goToPreviewPage}
                                        variant={props.isUpdate ? "secondary" : "primary"}>Preview
                                    </Button>
                                    {props.isUpdate ? (<Button
                                        disabled={publishing}
                                        isBusy={publishing}
                                        onClick={publishChanges}
                                        style={{ marginLeft: "10px" }}
                                        variant="primary" >Publish Changes
                                    </Button>) : (<></>)}
                                </ButtonGroup>
                            </div>)
                            : (<div className='navigation-footer-container'>
                                <Button
                                    className='navigation-footer-button fit-to-left'
                                    disabled={publishing}
                                    onClick={goToEditCampaignPage}
                                    variant="secondary">Back</Button>
                                <Button
                                    className='navigation-footer-button fit-to-right'
                                    disabled={publishing}
                                    isBusy={publishing}                                    
                                    onClick={publishChanges}
                                    variant="primary">Publish Changes</Button>
                            </div>)
                    }
                </CardFooter>
            </Card >
            {errorMessage && (
                <Modal icon={<Icon icon={warning} />} title={"Error"} onRequestClose={closeErrorModal} size={"small"}>
                    <p>{errorMessage}</p>
                    <Button variant="primary" onClick={closeErrorModal}>OK</Button>
                </Modal>
            )
            }
        </>
    );
};

export default CampaignSetupView;