# Quizzer plugin for glFusion
This plugin allows you to create simple multiple-choice quizzes on your site and collect responses.

## Creating a Quiz
From the Quizzer admin screen, click "New Quiz" and fill in the form.
### Quiz ID
Any string can be used here

### Quiz Name
This text is used as the title of the quiz.

### Intro Text
(optional) Enter some text to be displayed once on the first page of the quiz

### Text Fields
(optional) Enter a few field prompts separated by the pipe (|) character to collect text values for each submitter. Examples might include the submitter's name, department, etc.

### Enabled
Check if this quiz is enabled or not. Disabled quizzes can't be viewed.

### Per-User Submission Limit
Select whether or not one person can submit the quiz multiple times. To accept anonymous submissions this is effectively required.

### Number of Questions
Enter the number of questions to appear on the quiz. Up to this number of questions will be shown in random order. If there are fewer than this number in the pool then all questions will be shown.

### Scoring Levels
(optional) Up to three levels are available, based on Uikit styles: Good (green), Warning (yello/orange) and Fail (red). You can enter up to three percentage values here separated by the pipe (|) character.

Typically you would enter one or two values. For example "80" will show scores ov 80% and higher as "passing" and lower scores as "failing".

If zero is entered then all scores are considered "passing".
### Message if Passed
(optional) Enter a message to be displayed on the final screen if the score is passing, such as "Congratulations"

### Permissions
Group: Select the glFusion group that is able to see and fill out the quiz.

## Other Functions
### Results by Question/Submitter
View a report by question or submitter showing the overall scores.

#### By Submitter
Lists all submitters who have taken the quiz along with their overall score.

#### By Question
Lists all questions created for the quiz along with the overall score from all submitters.
May be useful in determining where people need more help or training.

### Export by Question/Submitter
Similar to the Results, these functions export a CSV file to be used in a spreadsheet.
