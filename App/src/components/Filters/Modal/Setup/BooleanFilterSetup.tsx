import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import RadioButtonsInput from '@/components/Inputs/RadioButtonsInput';
import { type FilterConfig } from '@/config/filterConfig';

interface BooleanFilterSetupProps {
	filterKey: string;
	config: FilterConfig;
	initialValue?: string;
	onChange: ( value: string ) => void;
}

interface RadioOption {
	type: string;
	icon: string;
	label: string;
	description?: string;
}

interface RadioOptions {
	[key: string]: RadioOption;
}

/**
 * Radio options per boolean filter key, built on call so translations are loaded.
 *
 * @return Options keyed by filter key.
 */
const getBooleanFilterOptions = (): Record<string, RadioOptions> => ({
	bounces: {
		'': {
			type: '',
			icon: 'user',
			label: __( 'All visitors (default)', 'burst-mainwp' )
		},
		include: {
			type: 'include',
			icon: 'bounce',
			label: __( 'Bounced', 'burst-mainwp' )
		},
		exclude: {
			type: 'exclude',
			icon: 'user-check',
			label: __( 'Active', 'burst-mainwp' )
		}
	},
	new_visitor: {
		'': {
			type: '',
			icon: 'user',
			label: __( 'All visitors (default)', 'burst-mainwp' )
		},
		include: {
			type: 'include',
			icon: 'user-plus',
			label: __( 'New', 'burst-mainwp' )
		},
		exclude: {
			type: 'exclude',
			icon: 'user-check',
			label: __( 'Returning', 'burst-mainwp' )
		}
	},
	sales: {
		'': {
			type: '',
			icon: 'user',
			label: __( 'All visits (default)', 'burst-mainwp' )
		},
		include: {
			type: 'include',
			icon: 'shopping-cart',
			label: __( 'Visits with an order', 'burst-mainwp' )
		},
		exclude: {
			type: 'exclude',
			icon: 'user',
			label: __( 'Visits without an order', 'burst-mainwp' )
		}
	},
	entry_exit_pages: {
		'': {
			type: '',
			icon: 'user',
			label: __( 'All pages', 'burst-mainwp' )
		},
		entry: {
			type: 'entry',
			icon: 'user-plus',
			label: __( 'Entry', 'burst-mainwp' )
		},
		exit: {
			type: 'exit',
			icon: 'user-check',
			label: __( 'Exit', 'burst-mainwp' )
		}
	},
	status: {
		all: {
			type: 'all',
			icon: 'total',
			label: __( 'All statuses', 'burst-mainwp' )
		},
		'200': {
			type: '200',
			icon: 'circle-check',
			label: __( 'OK', 'burst-mainwp' )
		},
		'404': {
			type: '404',
			icon: 'file-disabled',
			label: __( 'Not Found', 'burst-mainwp' )
		}
	},

	// Fallback for any other boolean filters.
	fallback: {
		'': {
			type: '',
			icon: 'user',
			label: __( 'All visitors (default)', 'burst-mainwp' ),
			description: __(
				'Show all visitors without filtering',
				'burst-mainwp'
			)
		},
		include: {
			type: 'include',
			icon: 'check',
			label: __( 'Include', 'burst-mainwp' ),
			description: __(
				'Include visitors matching this criteria',
				'burst-mainwp'
			)
		},
		exclude: {
			type: 'exclude',
			icon: 'times',
			label: __( 'Exclude', 'burst-mainwp' ),
			description: __(
				'Exclude visitors matching this criteria',
				'burst-mainwp'
			)
		}
	}
});

const BooleanFilterSetup: React.FC<BooleanFilterSetupProps> = ({
	filterKey,
	initialValue = '',
	onChange
}) => {
	const [ value, setValue ] = useState<string>( initialValue );

	useEffect( () => {
		setValue( initialValue );
	}, [ initialValue ]);

	const handleChange = ( newValue: string ) => {
		setValue( newValue );
		onChange( newValue );
	};

	const filterOptions = getBooleanFilterOptions();
	const radioOptions = filterOptions[filterKey] ?? filterOptions.fallback;

	return (
		<div className="flex flex-col gap-6">
			{/* Radio Options */}
			<div className="flex flex-col gap-3">
				<label className="block text-sm font-medium text-text-gray">
					{__( 'Filter option', 'burst-mainwp' )}
				</label>
				<RadioButtonsInput
					inputId={`${filterKey}-boolean`}
					options={radioOptions}
					value={value}
					onChange={handleChange}
					columns={1}
				/>
			</div>
		</div>
	);
};

export default BooleanFilterSetup;
