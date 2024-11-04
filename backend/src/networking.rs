use serde_json::Value;
use crate::panic_red;

fn github_request(url: &str, token: &str) -> Value {
    match ureq::get(url)
        .set("User-Agent", "fpps4.net")
        .set("Accept", "application/vnd.github+json")
        .set("Authorization", &format!("Bearer {}", token))
        .call()
    {
        Ok(response) => response.into_json().expect("Failed to parse JSON"),
        Err(response) => panic_red!("Github request failed: {}", response),
    }
}

// fn image_downloader(url: String, user_ffagent: &str, location: String) -> Result<(), anyhow::Error> {
//     let response = ureq::get(&url).set("User-Agent", user_agent).call()?;
//     let mut reader = response.into_reader();
//     let mut buffer = Vec::new();
//     reader.read_to_end(&mut buffer)?;
//
//     let img = image::load_from_memory(&buffer)?;
//     let resized_img = img.resize_exact(256, 256, image::imageops::Lanczos3);
//     resized_img.save_with_format(location, image::ImageFormat::Avif)?;
//     Ok(())
// }