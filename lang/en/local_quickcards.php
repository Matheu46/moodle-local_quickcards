<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Language strings for local_quickcards.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'QuickCards';
$string['quickcards:use'] = 'Use QuickCards spaced repetition';

// Navigation & General.
$string['mycards'] = 'My QuickCards';
$string['decks'] = 'Decks';
$string['study'] = 'Study';
$string['reviews'] = 'Reviews';
$string['totaldecks'] = 'Total Decks';
$string['totalcards'] = 'Total Cards';
$string['dueforreview'] = 'Due Reviews';
$string['studyall'] = 'Study All Due';
$string['managecards'] = 'Manage Cards';
$string['actions'] = 'Actions';
$string['course'] = 'Course';
$string['save'] = 'Save';
$string['cancel'] = 'Cancel';
$string['close'] = 'Close';
$string['delete'] = 'Delete';
$string['edit'] = 'Edit';

// Decks.
$string['deck'] = 'Deck';
$string['createdeck'] = 'Create Deck';
$string['editdeck'] = 'Edit Deck';
$string['deletedeck'] = 'Delete Deck';
$string['deckname'] = 'Deck Name';
$string['deckname_help'] = 'Give your deck a clear name, such as "Anatomy - Muscles" or "JavaScript Basics".';
$string['deckdescription'] = 'Description';
$string['nodecks'] = 'You have not created any decks yet.';
$string['nodecksdesc'] = 'Flashcard decks help you organize concepts and study with active recall. Create your first deck to get started!';
$string['coursedeck'] = 'Linked Course';
$string['generaldeck'] = 'General (Not tied to a course)';
$string['confirmdeletedeck'] = 'Are you sure you want to delete this deck? All its cards and review history will be permanently deleted.';
$string['resetprogress'] = 'Reset Progress';
$string['resetdeck'] = 'Reset Deck Progress';
$string['confirmresetdeck'] = 'Are you sure you want to reset the study progress for "{$a}"? All cards in this deck will be reset to 0 repetitions and scheduled for review today.';
$string['deckreset'] = 'Deck study progress has been reset successfully.';
$string['deckcreated'] = 'Deck created successfully.';
$string['deckupdated'] = 'Deck updated successfully.';
$string['deckdeleted'] = 'Deck deleted successfully.';

// Cards.
$string['card'] = 'Card';
$string['cards'] = 'Cards';
$string['addcard'] = 'Add Card';
$string['createcard'] = 'Add Card';
$string['editcard'] = 'Edit Card';
$string['deletecard'] = 'Delete Card';
$string['question'] = 'Front (Prompt / Question)';
$string['question_help'] = 'The question, term, or prompt that triggers active recall.';
$string['answer'] = 'Back (Answer / Explanation)';
$string['answer_help'] = 'The concise explanation or solution that you want to remember.';
$string['nocards'] = 'There are no cards in this deck yet.';
$string['nocardsdesc'] = 'Add cards with key concepts, definitions, or questions to start studying!';
$string['cardcount'] = '{$a} cards';
$string['confirmdeletecard'] = 'Are you sure you want to delete this card?';
$string['cardcreated'] = 'Card created successfully.';
$string['cardupdated'] = 'Card updated successfully.';
$string['carddeleted'] = 'Card deleted successfully.';
$string['movecard'] = 'Move Card';
$string['moveto'] = 'Move to Deck';
$string['selecttargetdeck'] = 'Select destination deck';
$string['cardmoved'] = 'Card moved successfully.';
$string['notargetdecks'] = 'You have no other decks to move this card to.';
$string['nextreview'] = 'Next Review';
$string['never'] = 'Never reviewed';
$string['today'] = 'Due today';
$string['inxdays'] = 'In {$a} days';

// Study & Review.
$string['studydeck'] = 'Study Deck';
$string['duereviews'] = 'Due for review today';
$string['noduecards'] = 'Great job! You have no cards due for review right now.';
$string['showanswer'] = 'Show Answer';
$string['showanswerspace'] = 'Show Answer (Space)';
$string['flipcard'] = 'Flip card';
$string['rating_again'] = 'Again (1)';
$string['rating_hard'] = 'Hard (2)';
$string['rating_good'] = 'Good (3)';
$string['rating_easy'] = 'Easy (4)';
$string['sessioncomplete'] = 'Session completed!';
$string['sessioncompletedesc'] = 'You have reviewed all due cards for this session.';
$string['backtodecks'] = 'Back to Decks';
$string['backtodeck'] = 'Back to Deck';
$string['cardof'] = 'Card {$a->current} of {$a->total}';
$string['congratulations'] = 'Congratulations!';
$string['studyagain'] = 'Study Again';
$string['keyboard_shortcuts'] = 'Shortcuts: [Space] Flip card &bull; [1] Again &bull; [2] Hard &bull; [3] Good &bull; [4] Easy';

// Search & Filters.
$string['searchcards'] = 'Search cards...';
$string['searchdecks'] = 'Search decks...';
$string['filterstatus'] = 'Filter by status';
$string['filtercourse'] = 'Filter by course';
$string['filterall'] = 'All';
$string['filterdue'] = 'Due';
$string['filternew'] = 'New';
$string['filterlearned'] = 'Learned';
$string['allcourses'] = 'All courses';
$string['withduecards'] = 'With due reviews';
$string['nocardsfound'] = 'No cards match your search or filter.';
$string['nodecksfound'] = 'No decks match your search or filter.';
$string['trydifferentsearch'] = 'Try searching with different keywords or reset your filters.';
$string['clearfilter'] = 'Clear filters';
$string['showingcount'] = 'Showing {$a->shown} of {$a->total} cards';
$string['showingdeckscount'] = 'Showing {$a->shown} of {$a->total} decks';

// Export & Import.
$string['exportdeck'] = 'Export Deck';
$string['exportdeckfor'] = 'Exporting deck:';
$string['exportcontentonly'] = 'Cards only (Recommended for sharing)';
$string['exportcontentonlydesc'] = 'Exports deck details and all flashcards without your personal review history. Ideal for sharing with classmates.';
$string['exportwithprogress'] = 'Cards + Study Progress (Personal backup)';
$string['exportwithprogressdesc'] = 'Includes your SM-2 intervals, ease factors, and repetition counts. Ideal for backing up or migrating your personal study progress.';
$string['exportjson'] = 'Download File';
$string['exportcsv'] = 'CSV — Spreadsheet / Anki / Quizlet';
$string['exportcsvdesc'] = 'Exports questions and answers as a standard CSV table. Compatible with Excel, Google Sheets, Anki, and Quizlet.';
$string['importdeck'] = 'Import Deck';
$string['importjson'] = 'Import JSON';
$string['importtabjson'] = 'QuickCards (JSON)';
$string['importtabcsv'] = 'CSV / Anki / Quizlet';
$string['selectcsvfile'] = 'Upload CSV / TXT file (optional)';
$string['selectcsvfile_help'] = 'Upload a .csv or .txt file, or paste your list directly below.';
$string['pastecsvdata'] = 'Paste cards (Question & Answer per line)';
$string['pastecsvdata_placeholder'] = "What is mitochondria?\tPowerhouse of the cell\nCapital of France\tParis";
$string['delimiter'] = 'Separator';
$string['delimiter_auto'] = 'Auto-detect (Recommended)';
$string['delimiter_tab'] = 'Tab (Anki / Quizlet default)';
$string['delimiter_semicolon'] = 'Semicolon (;)';
$string['delimiter_comma'] = 'Comma (,)';
$string['csvcardsdetected'] = '{$a} valid cards detected';
$string['selectjsonfile'] = 'Select QuickCards JSON file';
$string['selectjsonfile_help'] = 'Choose a .json file exported from QuickCards.';
$string['deckpreview'] = 'Deck Preview';
$string['cardsinfile'] = '{$a} cards found';
$string['containsstudyprogress'] = 'This file includes personal study progress.';
$string['restoreprogress'] = 'Restore study progress';
$string['restoreprogress_help'] = 'If enabled, previous review intervals and ease factors will be preserved. Leave unchecked to start studying these cards as new.';
$string['deckimported'] = 'Deck "{$a->name}" with {$a->count} cards imported successfully.';
$string['invalidfileformat'] = 'The selected file is not a valid QuickCards export.';
$string['invalidjson'] = 'Could not parse JSON from the selected file.';
$string['nodeckname'] = 'The export file does not contain a valid deck name.';
$string['nofileorcard'] = 'The export file does not contain any cards.';
$string['unsupportedversion'] = 'Unsupported export file version: {$a}.';

// Privacy.
$string['privacy:metadata'] = 'QuickCards stores user flashcards, decks, and spaced repetition review intervals.';
