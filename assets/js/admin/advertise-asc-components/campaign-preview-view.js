import { useState, useEffect } from '@wordpress/element';
import { Card, Space, Spin, Typography } from 'antd';
import { LoadingOutlined } from '@ant-design/icons'

const { Title } = Typography;

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
        <Spin spinning={loading}>
            {iFrame}
        </Spin>
    );
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

    if (result && result["data"]) {
        props.onSizeChange(750, 600);
        return (
            <Space direction='horizontal'>
                <>
                    {result["data"].map(function (o, i) {
                        const iframe = (<CampaignPreviewComponentView text={o} />);
                        return (i === result['data'].length - 1) ? iframe : (<div style={{ marginRight:'30px' }}>{iframe}</div>);
                    })}
                </>
            </Space>
        );
    }
    else {
        props.onSizeChange(750, 600);
        return (
            <Card className='fb-asc-ads loading-preview-parent'>
                <div className='fb-asc-ads loading-preview-container '>
                    <div>
                        <Title><LoadingOutlined /> Loading...</Title>
                    </div>
                </div>
            </Card>
        );
    };
};

export default CampaignPreviewView;