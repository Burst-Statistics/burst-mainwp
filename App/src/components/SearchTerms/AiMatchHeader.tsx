import { __ } from '@wordpress/i18n';
import Icon from '@/utils/Icon';

/**
 * Header of the SmartMatch "AI match" column.
 *
 * @return {JSX.Element} The column header.
 */
const AiMatchHeader = () => (
	<span className="inline-flex items-center gap-1.5">
		<Icon name="sparkles" size={14} color="blue" className="shrink-0" />
		{__( 'AI match', 'burst-mainwp' )}
	</span>
);

export default AiMatchHeader;
