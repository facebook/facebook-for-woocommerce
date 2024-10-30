import { useState, useEffect } from '@wordpress/element';
import { Flex, FlexItem, Spinner } from '@wordpress/components';

function ExtractIFrame(iFrameText, onLoaded) {
    const urlStartIndex = iFrameText.indexOf("src") + 5;
    const urlEndIndex = iFrameText.indexOf("\"", urlStartIndex + 1);

    const url = iFrameText.substring(urlStartIndex, urlEndIndex).replace('amp;', '');

    return (
        <div className='fb-asc-ads zero-border-element preview-object-iframe-parent' >
            <iframe className='fb-asc-ads zero-border-element preview-object-iframe'
                src={url}
                onLoad={() => { onLoaded(); }} />
        </div>
    );
}

const CampaignPreviewComponentView = (props) => {

    const [loading, setLoading] = useState(true);

    const iFrame = ExtractIFrame(props.text, () => { setLoading(false); });

    return (
        <>
            {loading ? <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center' }}><Spinner style={{ height: 'calc(4px * 5)', width: 'calc(4px * 5)' }} /></div> : <></>}
            {iFrame}
        </>
    )
};

const CampaignPreviewView = (props) => {

    const [result, setResult] = useState({});

    const url = facebook_for_woocommerce_settings_advertise_asc.ajax_url + (
        props.preview ? (
            '?action=wc_facebook_get_ad_preview&view=' + props.campaignType
        ) : (
            '?action=wc_facebook_generate_ad_preview&view=' + props.campaignType + '&message=' + encodeURIComponent(props.message)
        ));

    useEffect(() => {
        fetch(url)
            .then((response) => response.json())
            .then((data) => {
                setResult(data);
            });
    }, [setResult]);

    return (
        <Flex direction={['row']}>
            {result && result["data"] ? result["data"].map(function (o, i) {
                return <FlexItem><CampaignPreviewComponentView text={o} /></FlexItem>;
            }) : (
                <FlexItem>
                    <div className='fb-asc-ads loading-preview-parent'>
                        <div className='fb-asc-ads loading-preview-container'>
                            <Spinner style={{ height: 'calc(4px * 10)', width: 'calc(4px * 10)' }} />
                        </div>
                    </div>
                </FlexItem>
            )}
        </Flex >
    );
};

export default CampaignPreviewView;