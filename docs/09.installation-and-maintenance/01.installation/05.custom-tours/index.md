---
title: Creating custom tours for users environments
---

This guide explains how to create and configure custom tours for user environments.

# What Is a Custom Tour?

A custom tour is an interactive guidance mechanism that helps users become familiar with the system interface and functionality. Tours can be used to introduce new users to key features, explain important interface elements, and provide step-by-step instructions for completing common tasks.

Custom tours are displayed directly within the user interface and highlight specific fields, buttons, panels, or other UI elements together with descriptive text.

# How to Set Up a Custom Tour

To create a custom tour, navigate to the root directory of the project or module for which the tour should be available.

Create the following directory structure if it does not already exist:
```
metadata/
└── tourData/
```
Inside the `tourData` directory, create a JSON file that defines the tour configuration.

Each tour file contains one or more sections corresponding to system views, such as:

- edit — displayed on [edit views](../../../01.atrocore/04.understanding-ui/index.md#edit-view)
- detail — displayed on [detail views](../../../01.atrocore/04.understanding-ui/index.md#detail-view)
- list — displayed on [list views](../../../01.atrocore/04.understanding-ui/index.md#list-view)

Within each section, UI elements are identified using CSS selectors. For each selector, a description can be defined in one or more languages.

## Tour File Structure

The basic structure of a tour configuration file is:
```
{
  "edit": {
    "<css-selector>": {
      "description": {
        "en_US": "Description text"
      }
    }
  },
  "detail": {
    "<css-selector>": {
      "description": {
        "en_US": "Description text"
      }
    }
  }
}
```
## Configuration Elements

### View Type

The top-level object key defines the view where the tour step will be displayed.

Supported values include:
- edit
- detail

Each view can contain multiple tour steps.

### CSS Selector

Each tour step is associated with a CSS selector that identifies the UI element to be highlighted.

Examples:

```"[data-name=\"name\"]"```

```".panel-configuratorItems .panel-heading [data-action=\"createRelated\"]"```

The selector must uniquely identify a visible element on the page.

### Description

The description object contains the text displayed to the user when the tour reaches the selected element.

Descriptions support multiple languages.

Example:
```
{
  "description": {
    "en_US": "Set the name for your import feed."
  }
}

Additional languages can be added:

{
  "description": {
    "en_US": "Set the name for your import feed.",
    "de_DE": "Geben Sie den Namen Ihres Import-Feeds ein."
  }
}
```

## Example

The following example demonstrates a tour configuration for the Import Feeds module:

```
{
  "edit": {
    "[data-name=\"name\"]": {
      "description": {
        "en_US": "Set the name for your import feed."
      }
    }
  }
}
```

When a user opens the edit view, the tour highlights the Name field and displays the configured description.

## Best Practices

1) Use selectors that uniquely identify UI elements.
2) Keep descriptions concise and action-oriented.
3) Focus on explaining business-relevant functionality rather than obvious interface elements.
4) Provide translations for all supported system languages whenever possible.
5) Verify selectors after UI changes, as modified layouts may cause tour steps to stop working.
6) Test tours in the target environment before deployment.

## Deployment

After creating or modifying a tour file:

1) Save the JSON file in the metadata/tourData directory.
2) Clear the application cache if required.
3) Rebuild metadata if applicable.
4) Reload the application.

The custom tour will become available to users when they open the corresponding view.