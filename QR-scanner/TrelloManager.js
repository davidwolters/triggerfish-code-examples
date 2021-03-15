


/*jshint esversion: 6 */
/*global $:false */
/*global console:false */


class TrelloManager {



	static authorize(onSuccess, onError) {
		window.Trello.authorize({
			type: 	'redirect',
			name:	'Hello World! Incheckningssystem',
			scope: {
				read: true,
				write: true },
			expiration: 'never',
			success: onSuccess,
			error: onError
		});
	}

	static getLabelIDs(cardID, onSuccess, onError) {
		window.Trello.get('/cards/'+cardID+'/', {}, (card) => {
			let labels = [];
			for (let i = 0; i < card.idLabels.length; i++) {
				let id = card.idLabels[i];
				//let id = card.idLabels[labelIndex];
				labels.push(id);
			}
			onSuccess(labels);

		}, onError);
	}


	static getLabelInfo(labelID, onSuccess, onError) {
		window.Trello.get('/labels/'+labelID+'/', onSuccess, onError);
	}

	static getLabelsInfo(labels, onSuccess, onError) {

		let labelInfos = [];

		let labelIndex = 0;

		let processLabelInfo = label => {
			labelInfos.push(label);

			onSuccess(labelInfos);

		};

		for (labelIndex = 0; labelIndex < labels.length; labelIndex++) {
			TrelloManager.getLabelInfo(labels[labelIndex], processLabelInfo, onError);
		}
	}

	static getLabel(cardID, onSuccess, onError) {
		let foundLabel = false;
		window.Trello.get('/cards/'+cardID+'/', {}, (card) => {
			for (let i = 0; i < card.labels.length; i++) {
				let color = card.labels[i].color;

				if (color === 'red' || color === 'green') {
					foundLabel = true;
					onSuccess(card.labels[i]);
					return;
				}
			}

			if (!foundLabel) {
				onError(false);
			}


		}, () => {
			foundLabel = true;
			onError(true);
		});




	}

	static getCardStatus(cardID, onSuccess, onError) {
		TrelloManager.getLabel(cardID, label => {
			onSuccess(label.color === 'green');
		}, networkError => {
			onError(networkError);
		});
	}

	static removeLabel(cardID, labelID, onSuccess, onError) {
		window.Trello.del('cards/'+cardID+'/idLabels/'+labelID+'/',{}, onSuccess, onError);
	}

	static addLabel(cardID, labelName, labelColor, onSuccess, onError) {

		let label = {
			color: labelColor,
			name: labelName
		};

		window.Trello.post('/cards/'+cardID+'/labels/', label, onSuccess, onError);
	}

	static switchLabels(cardID, onSuccess, onError) {

		// First we need to get all the labels on the card...
		TrelloManager.getLabelIDs(cardID, labels => {
			// This is the state the card is in.
			let state = LabelState.Unknown;
			let finished = false;

			let labelIndex = 0;

			// What happens if we can't fetch information about the label?
			let noLabelInfo = () => {
				onError('Could not fetch information about the label');
			};

			// Switches the label from red to green or vice versa.
			let switchLabel = label => {
				// Assign the label a state...
				if (label.color === Labels.Colors.Red) {
					state = LabelState.CheckedOut;
				} else if (label.color === Labels.Colors.Green) {
					state = LabelState.CheckedIn;
				} else {
					state = LabelState.Unknown;
				}

				// If we have found a red or green label, and we haven't before...
				if (state !== LabelState.Unknown && !finished) {

					// Now we have.
					finished = true;

					// Assign the labelID to the current label.

					// Now we switch the labels.
					let name = (state === LabelState.CheckedOut) ? Labels.Names.CheckedIn : Labels.Names.CheckedOut;
					let color = (state === LabelState.CheckedOut) ? Labels.Colors.Green : Labels.Colors.Red;

					// Remove the previous label...
					TrelloManager.removeLabel(cardID, label.id, () => {
						// And add a new label.
						TrelloManager.addLabel(cardID, name, color, onSuccess, () => {
							onError('Could not add a label to the card.');
						});
					}, () => {
						onError('Could not remove the label from the card');
					});
				} else if (labelIndex === labels.length - 1) {
					onError('There was no state indicating label');
				}
			};

			// Iterate over the labels...
			for (labelIndex = 0; labelIndex < labels.length; labelIndex++) {
				// Get info about each label...
				TrelloManager.getLabelInfo(labels[labelIndex], switchLabel, noLabelInfo);
			}
		}, () => {
			onError('Could not retrieve the labels');
		});
	}
}


const Labels = {
	Colors: {
		Red: 'red',
		Green: 'green',
	},
	Names: {
		CheckedIn: 'Incheckad',
		CheckedOut: 'Utcheckad',
	}
};

const LabelState = {
	CheckedIn 	: 1,
	Unknown 	: 0,
	CheckedOut 	: -1
};