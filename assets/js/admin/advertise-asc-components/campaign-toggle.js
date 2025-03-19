import { useState } from '@wordpress/element';
import { ToggleControl } from '@wordpress/components';

const CampaignToggle = (props) => {
    const [isChecked, setIsChecked] = useState(props.checked);

    return (
        <ToggleControl
            {...props}
            checked={isChecked}
            onChange={(state) => {
                setIsChecked(state);
                props?.onChange(state);
            }}
			label={props.label ? props.label : ""}
        />
    );
};

export default CampaignToggle;