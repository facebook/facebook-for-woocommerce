import { useState } from '@wordpress/element';
import { Icon, __experimentalNumberControl as NumberControl, Notice, __experimentalHeading as Heading, Flex, FlexItem, TextareaControl, Tooltip } from '@wordpress/components';
import { edit, globe, helpFilled, currencyDollar } from '@wordpress/icons';
import { CountryList } from './eligible-country-list'
import CampaignToggle from './campaign-toggle'
import CountrySelector from './country-selector'

const CampaignEditView = (props) => {

    const title = props.isRetargeting ? 'Retargeting Campaign' : 'New Customers Campaign';
    const subtitle = props.isRetargeting ? "Bring back visitors who visited your website and didn't complete their purchase" : "Reach out to potential new buyers for your products";
    const [message, setMessage] = useState(props.message ?? "");
    const [dailyBudget, setDailyBudget] = useState(props.dailyBudget ?? 0);
    const [status, setStatus] = useState(props.currentStatus ?? false);
    const [selectedCountries, setSelectedCountries] = useState(props.selectedCountries ?? []);

    const countryPairs = CountryList.map((c) => {
        return {
            key: Object.keys(c)[0],
            value: Object.values(c)[0]
        }
    });
    const availableOptions = countryPairs.filter(x => { return selectedCountries.indexOf(x) == -1; });

    return (
        <div className="fb-asc-ads default-view">
            <Heading level={3}>{title}</Heading>
            <Heading level={4} variant={"muted"} weight={300}>{subtitle}</Heading>
            {props.invalidInputMessage.length > 0 && (<Notice status="warning" isDismissible={false}><ul>{props.invalidInputMessage.map((msg) => { return <li>{msg}</li> })}</ul></Notice>)}
            <Flex direction={['row']} gap={1} style={{ alignItems: "start" }}>
                <FlexItem style={{ width: "300px", maxWidth: "300px", width: "300px" }}>
                    <p className='zero-border-element campaign-edit-view-header'>
                        <Icon icon={currencyDollar} size={22} />
                        {' '} Campaign Off/On {' '}
                        <Tooltip text="Do you want your campaign to actively run? Make sure to select 'On'">
                            <Icon icon={helpFilled} size={12} className='campaign-edit-view-header-tooltip' />
                        </Tooltip>
                    </p>
                    <div className='zero-border-element'>
                        <CampaignToggle
                            className='zero-border-element'
                            checked={status}
                            onChange={(new_value) => {
                                setStatus(new_value);
                                props.onStatusChange(new_value);
                            }}
                        />
                    </div>
                    <p className='campaign-edit-view-header'>
                        <Icon icon={currencyDollar} size={22} /> {' '} Daily Budget {' '}
                        <Tooltip text="How much would you want to spend on a daily basis?">
                            <Icon icon={helpFilled} size={12} className='campaign-edit-view-header-tooltip' />
                        </Tooltip>
                    </p>
                    <div className='zero-border-element'>
                        <NumberControl
                            className='zero-border-element'
                            isDragEnabled={false}
                            isShiftStepEnabled={false}
                            onChange={(new_value) => {
                                setDailyBudget(new_value);
                                props.onDailyBudgetChange(new_value);
                            }}
                            prefix={props.currency}
                            required={true}
                            step="0.1"
                            type={"number"}
                            value={dailyBudget}
                        />
                    </div>
                    {!props.isRetargeting && (
                        <div>
                            <p className='campaign-edit-view-header'>
                                <Icon icon={globe} size={22} /> {' '} Country {' '} <Tooltip text="Countries where your campaign will be shown."><Icon icon={helpFilled} size={12} className='campaign-edit-view-header-tooltip' /></Tooltip>
                            </p>
                            <CountrySelector
                                maxLength={5}
                                options={availableOptions.map((item) => ({
                                    key: item['key'],
                                    label: item['value']
                                }))}
                                value={selectedCountries}
                                onChange={(new_values) => {
                                    setSelectedCountries(new_values);
                                    props.onCountryListChange(new_values);
                                }}
                            />
                        </div>
                    )}
                </FlexItem>
                <FlexItem style={{ width: "300px", maxWidth: "300px", width: "300px" }}>
                    <div className='zero-border-element'>
                        <p className='campaign-edit-view-header'>
                            <Icon icon={edit} size={22} /> {' '} Customize your message
                            <Tooltip text="The pitch for selling your products. Choose it wisely!">
                                <Icon icon={helpFilled} size={12} className='campaign-edit-view-header-tooltip' />
                            </Tooltip>
                        </p>
                        <p className='zero-border-element campaign-edit-secondary-header' >{'The carousel will show your products'}</p>
                    </div>
                    <div className='transparent-background campaign-edit-view-thumbnail-container'>
                        <img className='campaign-edit-view-img' src={require('!!url-loader!./../../../images/woo_post1.png').default} />
                        <TextareaControl
                            className='campaign-edit-view-messagebox'
                            rows="2"
                            onChange={(new_value) => {
                                setMessage(new_value);
                                props.onMessageChange(new_value);
                            }}
                            value={message}
                        />
                        <img className='campaign-edit-view-img' style={{ marginRight: 0 }} src={require('!!url-loader!./../../../images/woo_post2.png').default} />
                    </div>
                </FlexItem>
            </Flex>
        </div>
    );
};

export default CampaignEditView;