use anyhow::{bail, Context};
use regex::Regex;
use serde::{Deserialize, Serialize};
use serde_json::Value;

struct Issues {
    issues: Vec<Issue>,
}

// #[derive(Serialize, Deserialize)]
struct Issue {
    id: u64,
    code: String,
    title: String,
    labels: Vec<String>,
    status: Status,
    issue_type: GameType,
    created: String,
    updated: String,
    // image: bool,
}

// #[derive(Serialize, Deserialize)]
enum GameType {
    Game,
    Homebrew,
    Ps2game,
    SystemFw505,
    SystemFwUnknown,
}

// #[derive(Serialize, Deserialize)]
enum Status {
    Playable,
    Ingame,
    Menus,
    Boots,
    Nothing,
}

fn parse_github_issue(issue: Value, code_regex: Regex) -> anyhow::Result<(Issue, Vec<String>)> {
    let mut warning: Vec<String> = vec![];

    let id: u64 = issue
        .get("number")
        .and_then(Value::as_u64)
        .context("Failed to parse id")?;

    let code: String = {
        let title: &str = issue
            .get("title")
            .and_then(Value::as_str)
            .context("Failed to parse title")?;

        if code_regex.captures(&title).iter().len() != 1 {
            warning.push(String::from("More than one title id has been matched")); // add a warning
        }

        code_regex
            .find(&title)
            .map(|x| x.as_str().to_uppercase())
            .context("Failed to get code using regex")?
    };

    let title: String = {
        let mut title: String = issue
            .get("title")
            .and_then(Value::as_str)
            .context("Failed to parse title")?
            .to_string();

        title = title.replace(&code, "");

        if title.contains("(Homebrew)")
            || title.contains("- HOMEBREW")
            || title.contains("Homebrew")
            || title.contains("[]")
        {
            warning.push(String::from("Title not correctly formatted")); // add a warning

            title = title.replace("(Homebrew)", "");
            title = title.replace("- HOMEBREW",    "");
            title = title.replace("Homebrew", "");
            title = title.replace("[]", "");
        }

        title
    };

    let mut labels: Vec<&str> = issue
            .get("labels")
            .and_then(Value::as_array)
            .context("Failed to get labels")?
            .iter()
            .map(|label| label
                .get("name")
                .and_then(Value::as_str)
                .unwrap_or_default())
            .collect::<Vec<&str>>();


    let status: Status = {
        
        // I agree that the .retain is a bit silly, but I want to be able to give a warning when an issue has more than one "status-" label
        let status = match labels.iter().find(|label| label.starts_with("status-")) {
            Some(label) => match *label {
                "status-playable" => {
                    labels.retain(|label| label != &"status-playable");
                    Status::Playable
                }
                "status-ingame" => {
                    labels.retain(|label| label != &"status-ingame");
                    Status::Ingame
                }
                "status-menus" => {
                    labels.retain(|label| label != &"status-menus");
                    Status::Menus
                }
                "status-boots" => {
                    labels.retain(|label| label != &"status-boots");
                    Status::Boots
                }
                "status-nothing" => {
                    labels.retain(|label| label != &"status-nothing");
                    Status::Nothing
                }
                _ => bail!("No status label found or recognized"),
            }
            None => bail!("No status label found or recognized"),
        };

        // clean labels
        if labels.contains(&"status-") 
        {
            warning.push(String::from("More than one status label")); // add a warning
            labels = labels.into_iter().filter(|x| !x.starts_with("status-")).collect();
        }

        status
    };


    let issue_type: GameType = {
        let issue_type = match labels.iter().find(|label| label.starts_with("app-")) {
            Some(label) => match *label {
                "app-system-fw505" => {
                    labels.retain(|label| *label != "app-system-fw505");
                    GameType::SystemFw505
                }
                "app-system-fw_unknown" => {
                    labels.retain(|label| *label != "app-system-fw_unknown");
                    GameType::SystemFwUnknown
                }
                "app-ps2game" => {
                    labels.retain(|label| *label != "app-ps2game");
                    GameType::Ps2game
                }
                "app-homebrew" => {
                    labels.retain(|label| *label != "app-homebrew");
                    GameType::Homebrew
                }
                    
                _ => 
                    if code.starts_with("CUSA")
                        || code.starts_with("PCJS")
                        || code.starts_with("PLJM")
                        || code.starts_with("PLJS")
                    {
                        GameType::Game
                    } else {
                        bail!("couldn't determine the game-type")
                    }
                
            }
            
            None => 
                if code.starts_with("CUSA")
                    || code.starts_with("PCJS")
                    || code.starts_with("PLJM")
                    || code.starts_with("PLJS")
                {
                    GameType::Game
                } else {
                    bail!("couldn't determine the game-type")
                }
            
        };

        // clean labels
        if labels.contains(&"app-system-fw505")
            || labels.contains(&"app-system-fw_unknown")
            || labels.contains(&"app-ps2game")
            || labels.contains(&"app-homebrew")
        {
            warning.push(String::from("More than one app label")); // add a warning
            labels = labels.into_iter().filter(|x| !x.starts_with("app-")).collect();
        }

        issue_type
    };

    // RFC3339 String
    let created: String = issue
        .get("created_at")
        .and_then(Value::as_str)
        .context("Failed to parse updated_at")?
        .to_string();

    let updated: String = issue
        .get("updated_at")
        .and_then(Value::as_str)
        .context("Failed to parse updated_at")?
        .to_string();

    let new_issue: Issue = Issue {
        id,
        code,
        title,
        labels: labels
            .iter_mut()
            .map(|label| label.to_string())
            .collect(),
        status,
        issue_type,
        created,
        updated,
    };

    Ok((new_issue, warning))
}