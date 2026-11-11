import Modal from '@/components/Common/Modal';
import { formatUnixToDate } from '../../utils/formatting';
import { __ } from '@wordpress/i18n';
import { Close } from '@radix-ui/react-dialog';
import { useState } from 'react';
import ButtonInput from '../Inputs/ButtonInput';

const Content = ({ goal }) => (
	<>
		{__( 'Are you sure you want to delete this goal?', 'burst-mainwp' ) +
			' ' +
			__( 'This action cannot be undone.', 'burst-mainwp' )}
		<br />
		<br />
		<strong>{__( 'Goal name', 'burst-mainwp' )}:</strong> {goal.name}
		<br />
		<strong>{__( 'Status', 'burst-mainwp' )}:</strong> {goal.status}
		<br />
		<strong>{__( 'Date created', 'burst-mainwp' )}:</strong>{' '}
		{formatUnixToDate( goal.dateCreated )}
	</>
);

const Footer = ({ deleteGoal, onClose, isDisabled }) => {
	return (
		<>
			<Close asChild aria-label="Close">
			<ButtonInput btnVariant={'tertiary'} onClick={onClose}>
				{__( 'Cancel', 'burst-mainwp' )}
			</ButtonInput>

			</Close>
			<ButtonInput btnVariant={'secondary'} onClick={deleteGoal} disabled={isDisabled}>
				{__( 'Delete goal', 'burst-mainwp' )}
			</ButtonInput>
		</>
	);
};

/**
 * Confirmation before a goal is deleted. Opened from the goal's actions menu.
 *
 * @param {Object}   props            Component props.
 * @param {Object}   props.goal       Name, status label and creation date of the goal.
 * @param {Function} props.deleteGoal Deletes the goal.
 * @param {boolean}  props.isOpen     Whether the modal is open.
 * @param {Function} props.onClose    Closes the modal.
 * @return {JSX.Element} The modal.
 */
const DeleteGoalModal = ({ goal, deleteGoal, isOpen, onClose }) => {
	const [ isDisabled, setDisabled ] = useState( false );

	const handleDelete = async() => {
		setDisabled( true );
		try {
			await deleteGoal();
		} finally {
			setDisabled( false );
			onClose();
		}
	};

	return (
		<Modal
			isOpen={isOpen}
			onClose={onClose}
			title={__( 'Delete goal', 'burst-mainwp' )}
			content={<Content goal={goal} />}
			footer={
				<Footer
					deleteGoal={handleDelete}
					onClose={onClose}
					isDisabled={isDisabled}
				/>
			}
		/>
	);
};

export default DeleteGoalModal;
