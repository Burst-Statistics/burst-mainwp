import React, { useState, forwardRef } from 'react';
import { __ } from '@wordpress/i18n';
import FieldWrapper from '@/components/Fields/FieldWrapper';
import TextInput from '@/components/Inputs/TextInput';
import ButtonInput from '@/components/Inputs/ButtonInput';
import Modal from '@/components/Common/Modal';
import Icon from '@/utils/Icon';
import { doAction } from '@/utils/api';

interface SlackWebhookFieldProps {
	field: {
		name: string;
		value: string;
		onChange: ( value: string ) => void;
		onBlur?: () => void;
		ref?: React.Ref<HTMLInputElement>;
	};
	fieldState?: {
		error?: {
			message?: string;
		};
	};
	help?: string;
	context?: React.ReactNode;
	id?: string;
	disabled?: boolean;
	[key: string]: any; // eslint-disable-line @typescript-eslint/no-explicit-any
}

// fallow-ignore-next-line complexity
const SlackWebhookField = forwardRef<HTMLDivElement, SlackWebhookFieldProps>(
	({ field, fieldState, help, context, disabled = false, ...props }, ref ) => {
		const [ isTesting, setIsTesting ] = useState( false );
		const [ testResult, setTestResult ] = useState<{ success: boolean; message: string } | null>( null );
		const [ isHelpModalOpen, setIsHelpModalOpen ] = useState( false );

		const inputId = props.id || field.name;

		const currentValue = field.value !== undefined ? field.value : ( props.setting?.value || '' );
		const hasValue = Boolean( currentValue && 0 < currentValue.trim().length );
		const isMasked = Boolean( currentValue && currentValue.includes( '•' ) );

		// fallow-ignore-next-line complexity
		const handleSendTest = async() => {
			const webhookUrl = currentValue ? currentValue.trim() : '';

			if ( ! webhookUrl ) {
				setTestResult({
					success: false,
					message: __( 'Please enter a Slack webhook URL first.', 'burst-mainwp' )
				});
				return;
			}

			setIsTesting( true );
			setTestResult( null );

			try {
				const response: any = await doAction( 'slack_test', { // eslint-disable-line @typescript-eslint/no-explicit-any
					webhook_url: webhookUrl
				});

				if ( response && response.success ) {
					setTestResult({
						success: true,
						message: response.message || __( 'Test message sent successfully!', 'burst-mainwp' )
					});
				} else {
					setTestResult({
						success: false,
						message: ( response && response.message ) || __( 'Failed to send test message.', 'burst-mainwp' )
					});
				}
			} catch ( error: any ) { // eslint-disable-line @typescript-eslint/no-explicit-any
				setTestResult({
					success: false,
					message: error?.message || __( 'Network error while testing webhook.', 'burst-mainwp' )
				});
			} finally {
				setIsTesting( false );
			}
		};

		return (
			<div ref={ref} className="w-full">
				<FieldWrapper
					inputId={inputId}
					help={help}
					error={fieldState?.error?.message}
					context={context}
					label={props.label || __( 'Slack webhook URL', 'burst-mainwp' )}
					disabled={disabled}
					className={props.className}
					{...props}
				>
					<div className="flex flex-col gap-2 mt-1">
						<div className="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
							<div className="relative flex-1 min-w-0">
								<TextInput
									id={inputId}
									name={field.name}
									value={currentValue}
									onChange={( e ) => {
										setTestResult( null );
										field.onChange( e.target.value );
									}}
									onBlur={field.onBlur}
									placeholder="https://hooks.slack.com/services/..."
									disabled={disabled}
									className={`w-full font-mono text-sm ${hasValue && ! disabled ? 'pr-8' : ''}`}
								/>
								{hasValue && ! disabled && (
									<button
										type="button"
										onClick={() => {
											field.onChange( '' );
											setTestResult( null );
										}}
										title={__( 'Clear webhook URL', 'burst-mainwp' )}
										className="absolute right-2.5 top-1/2 -translate-y-1/2 text-text-gray hover:text-text-black p-0.5 cursor-pointer focus:outline-hidden"
									>
										<Icon name="close" size={14} />
									</button>
								)}
							</div>
							<ButtonInput
								btnVariant="tertiary"
								size="md"
								onClick={handleSendTest}
								disabled={disabled || isTesting || ! hasValue}
								className="whitespace-nowrap shrink-0 flex items-center justify-center"
							>
								{isTesting ? (
									<span className="flex items-center gap-1.5">
										<Icon name="loading" size={14} className="animate-spin" />
										{__( 'Sending…', 'burst-mainwp' )}
									</span>
								) : (
									__( 'Send test message', 'burst-mainwp' )
								)}
							</ButtonInput>
						</div>

						{/* Saved / Unsaved status indicator */}
						{isMasked && (
							<div className="flex items-center gap-1.5 text-xs text-primary font-medium mt-0.5">
								<Icon name="check" size={13} className="shrink-0" />
								<span>{__( 'Slack webhook configured and saved', 'burst-mainwp' )}</span>
							</div>
						)}
						{hasValue && ! isMasked && (
							<div className="flex items-center gap-1.5 text-xs text-text-gray mt-0.5">
								<span>{__( 'Unsaved changes — click Save at the bottom to apply.', 'burst-mainwp' )}</span>
							</div>
						)}

						{/* Inline info and guide link row */}
						<div className="flex flex-wrap items-center justify-between gap-2 text-xs text-text-gray mt-0.5">
							<span>
								{__(
									'Anyone with access to the Slack channel will be able to open story links shared in notifications.',
									'burst-mainwp'
								)}
							</span>
							<button
								type="button"
								onClick={() => setIsHelpModalOpen( true )}
								className="inline-flex items-center gap-1 text-primary hover:underline font-medium cursor-pointer shrink-0 focus:outline-none"
							>
								<Icon name="help" size={13} />
								<span>{__( 'How to set up a Slack incoming webhook', 'burst-mainwp' )}</span>
							</button>
						</div>

						{/* Test Feedback */}
						{testResult && (
							<div
								className={`p-3 rounded-lg text-sm flex items-center gap-2.5 border transition-all mt-1 ${
									testResult.success ?
										'bg-primary-50 text-text-black border-primary' :
										'bg-red-50 text-text-black border-red'
								}`}
							>
								<Icon
									name={testResult.success ? 'check' : 'warning'}
									size={16}
									className={`shrink-0 ${testResult.success ? 'text-primary' : 'text-red'}`}
								/>
								<span className="leading-relaxed">{testResult.message}</span>
							</div>
						)}
					</div>
				</FieldWrapper>

				{/* Help Guide Modal */}
				<Modal
					isOpen={isHelpModalOpen}
					onClose={() => setIsHelpModalOpen( false )}
					title={__( 'Setting up a Slack incoming webhook', 'burst-mainwp' )}
					subtitle={__( 'Follow these steps to generate a webhook URL in Slack', 'burst-mainwp' )}
					content={
						<div className="flex flex-col gap-4 text-sm text-text-black py-2">
							<p className="text-text-gray leading-relaxed">
								{__(
									'Incoming webhooks allow Burst Statistics to post story reports, traffic spike/dip alerts, and tracking health notifications into your Slack channel.',
									'burst-mainwp'
								)}
							</p>

							<ol className="list-decimal pl-5 space-y-3 text-text-gray leading-relaxed">
								<li>
									<span className="font-semibold text-text-black">{__( 'Create a Slack app:', 'burst-mainwp' )}</span>{' '}
									{__( 'Go to', 'burst-mainwp' )}{' '}
									<a
										href="https://api.slack.com/apps"
										target="_blank"
										rel="noopener noreferrer"
										className="text-primary hover:underline focus:outline-none inline-flex items-center gap-1 font-medium"
									>
										api.slack.com/apps <Icon name="external-link" size={12} />
									</a>{' '}
									{__( 'and click', 'burst-mainwp' )}{' '}
									<strong className="text-text-black">{__( '"Create New App" → "Blank app"', 'burst-mainwp' )}</strong>.{' '}
									{__( 'Give it a name (e.g., "Burst Statistics") and choose your workspace.', 'burst-mainwp' )}
								</li>

								<li>
									<span className="font-semibold text-text-black">{__( 'Enable webhooks:', 'burst-mainwp' )}</span>{' '}
									{__( 'In your app settings, click on', 'burst-mainwp' )}{' '}
									<strong className="text-text-black">{__( '"Incoming Webhooks"', 'burst-mainwp' )}</strong>{' '}
									{__( 'in the left menu and toggle the switch to', 'burst-mainwp' )}{' '}
									<strong className="text-text-black">{__( '"On"', 'burst-mainwp' )}</strong>.
								</li>

								<li>
									<span className="font-semibold text-text-black">{__( 'Add webhook to workspace:', 'burst-mainwp' )}</span>{' '}
									{__( 'Click the', 'burst-mainwp' )}{' '}
									<strong className="text-text-black">{__( '"Add New Webhook to Workspace"', 'burst-mainwp' )}</strong>{' '}
									{__( 'button at the bottom of the page.', 'burst-mainwp' )}
								</li>

								<li>
									<span className="font-semibold text-text-black">{__( 'Select channel:', 'burst-mainwp' )}</span>{' '}
									{__( 'Choose the channel where you would like notifications to appear and click', 'burst-mainwp' )}{' '}
									<strong className="text-text-black">{__( '"Allow"', 'burst-mainwp' )}</strong>.
								</li>

								<li>
									<span className="font-semibold text-text-black">{__( 'Copy and paste:', 'burst-mainwp' )}</span>{' '}
									{__( 'Copy the generated webhook URL (starts with', 'burst-mainwp' )}{' '}
									<code className="bg-white text-text-black border border-border px-1.5 py-0.5 rounded text-xs font-mono">
										https://hooks.slack.com/services/...
									</code>
									{__( ') and paste it into the field above.', 'burst-mainwp' )}
								</li>

								<li>
									<span className="font-semibold text-text-black">{__( 'Test and save:', 'burst-mainwp' )}</span>{' '}
									{__( 'Click "Send test message" to verify delivery, then click "Save" on this settings page.', 'burst-mainwp' )}
								</li>
							</ol>

							<div className="bg-yellow-50 border border-yellow-200 rounded-md p-3 text-xs text-text-black mt-2 leading-relaxed">
								<strong className="font-semibold">{__( 'Security note:', 'burst-mainwp' )}</strong>{' '}
								{__(
									'Treat your webhook URL like a password. It gives permission to post messages to your channel. Burst Statistics masks this URL once saved and excludes it from diagnostic exports.',
									'burst-mainwp'
								)}
							</div>
						</div>
					}
					footer={
						<div className="flex justify-end">
							<ButtonInput
								btnVariant="tertiary"
								size="sm"
								onClick={() => setIsHelpModalOpen( false )}
							>
								{__( 'Close', 'burst-mainwp' )}
							</ButtonInput>
						</div>
					}
				/>
			</div>
		);
	}
);

SlackWebhookField.displayName = 'SlackWebhookField';
export default SlackWebhookField;
