import { useState } from '@wordpress/element';
import { CustomSelectControlV2, FormTokenField } from '@wordpress/components';

const __CountrySelectorMulti = (props) => {
    const [value, setValue] = useState(props.value);

    return (
        <CustomSelectControlV2
            {...props}
            onChange={(nextValue) => {
                setValue(nextValue);
                props.onChange?.(nextValue);
            }}
            defaultValue={value}
        >
            {props.options?.map((option) => (
                <CustomSelectControlV2.Item value={option.value}>
                    {option.label}
                </CustomSelectControlV2.Item>
            ))}
        </CustomSelectControlV2>
    );
};


const CountrySelector = (props) => {
    const mapLabelToKey = props.options?.reduce(function (map, obj) {
        map.set(obj.label, obj.key);
        return map;
    }, new Map());
    const mapKeyToLabel = props.options?.reduce(function (map, obj) {
        map.set(obj.key, obj.label);
        return map;
    }, new Map());

    const [value, setValue] = useState(props.value?.map((item) => mapKeyToLabel.get(item) || ""));

    return (
        <FormTokenField
            __experimentalValidateInput={(value) => {
                return mapLabelToKey.get(value) ? true : false;
            }}
            __experimentalShowHowTo={false}
            {...props}
            label={""}
            onChange={(nextValue) => {
                const new_values = nextValue.map((val) => mapLabelToKey.get(val) || "");
                setValue(nextValue);
                props.onChange?.(new_values);
            }}
            suggestions={props.options?.map((option) => option.label)}
            value={value}
        />
    );
};


export default CountrySelector;