import { useState } from '@wordpress/element';
import { Icon, __experimentalNumberControl as NumberControl, Notice, __experimentalHeading as Heading, TextareaControl, Tooltip } from '@wordpress/components';
import { info } from '@wordpress/icons';
import { CountryList } from './eligible-country-list'
import CampaignToggle from './campaign-toggle'
import CountrySelector from './country-selector'

const CampaignEditView = (props) => {

    const title = props.isRetargeting ? 'Retargeting Campaign' : 'Create New Customers Campaign';
    const subtitle = props.isRetargeting ? "Bring back visitors who visited your website and didn't complete their purchase" : "Reach out to potential new buyers for your products.";
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
        <div className="fb-asc-ads default-view edit-view">
            <Heading className='edit-view-title' level={3}>{title}</Heading>
            <Heading className='edit-view-subtitle' level={4} variant={"muted"} weight={300}>{subtitle}</Heading>

            {props.invalidInputMessage.length > 0 && (<Notice status="warning" isDismissible={false}><ul>{props.invalidInputMessage.map((msg) => { return <li>{msg}</li> })}</ul></Notice>)}

			<div className='edit-view-toggle zero-border-element'>
				<CampaignToggle
					className='zero-border-element'
					checked={status}
					onChange={(new_value) => {
						setStatus(new_value);
						props.onStatusChange(new_value);
					}}
					label='Toggle Campaign'
				/>

				<p className='zero-border-element'>Your ad will continue to run on a daily budget unless you pause it, which you can do at any time.</p>
			</div>

			<div className='edit-view-input-wrapper'>
				<div>
					<div>
						<NumberControl
							className='zero-border-element edit-view-number-control'
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
							label="Daily Budget"
						/>

						<p className='zero-border-element'>
							The actual amount spent daily may vary.
						
							<Tooltip text="Automatically distribute your budget to the best opportunities across your campaign. Also known as Advantage campaign budget.">
								<Tooltip text="Nested tooltip text (that will never show)">
									<Icon icon={info} size={22} />
								</Tooltip>
							</Tooltip>
						</p>
					</div>

					{!props.isRetargeting && (
						<div>
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
								label="Country"
							/>
						</div>
					)}
				</div>

				<div className='transparent-background campaign-edit-view-thumbnail-container'>
					<TextareaControl
						className='campaign-edit-view-messagebox'
						rows="4"
						onChange={(new_value) => {
							setMessage(new_value);
							props.onMessageChange(new_value);
						}}
						value={message}
						label="Message"
					/>
				</div>
			</div>
        </div>
    );
};

export default CampaignEditView;