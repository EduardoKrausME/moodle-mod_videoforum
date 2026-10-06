@mod @mod_videoforum @javascript
Feature: Participate in and moderate a Video Forum
  In order to discuss course content through short videos
  As a learner or teacher
  I need a video-first threaded activity with server-enforced visibility

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | One      |
      | student2 | Student   | Two      |
      | teacher1 | Teacher   | One      |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity   | name        | intro                | course | idnumber |
      | videoforum | Video Forum | Discuss using video. | C1     | vf1      |

  Scenario: Learner opens the vertical video feed and a thread
    Given the following "mod_videoforum > posts" exist:
      | videoforum | user     | title     |
      | vf1        | student1 | Topic one |
    When I am on the "Video Forum" "videoforum activity" page logged in as student2
    Then I should see "Topic one"
    And I should see "0 replies"
    When I click on "0 replies" "link"
    Then I should see "Topic one"
    And I should see "Reply"

  Scenario: Teacher hides a learner publication
    Given the following "mod_videoforum > posts" exist:
      | videoforum | user     | title          |
      | vf1        | student1 | Moderate this  |
    When I am on the "Video Forum" "videoforum activity" page logged in as teacher1
    Then I should see "Moderate this"
    When I click on "Hide" "button"
    Then I should see "This publication is hidden."
    When I am on the "Video Forum" "videoforum activity" page logged in as student2
    Then I should not see "Moderate this"
