use fast_image_resize::images::Image;
use fast_image_resize::{FilterType, PixelType, ResizeAlg, ResizeOptions, Resizer, SrcCropping};
use load_image::export::imgref::ImgVec;
use ravif::{AlphaColorMode, ColorSpace, EncodedImage, Encoder, Img, RGBA8};
use load_image::ImageData;

const RESIZE_OPTIONS: ResizeOptions = ResizeOptions {
    algorithm: ResizeAlg::Convolution(FilterType::Lanczos3),
    cropping: SrcCropping::None,
    mul_div_alpha: true,
};

pub(crate) fn convert_image(image_data: Vec<u8>) -> anyhow::Result<Vec<u8>> {
    
    let quality: f32 = 60f32;
    let alpha_quality = ((quality + 100.)/2.).min(quality + quality/4. + 2.);
    let speed: u8 = 1;
    let color_space = ColorSpace::YCbCr;
    let depth = Some(10);

    let img = load_rgba(image_data)?;

    let cropped_image = crop_image(img)?;

    // let img: Img<Vec<u8>> = Img::new(cropped_image, 128,128);

    let rgba_pixels: Vec<RGBA8> = cropped_image
        .chunks(4)
        .map(|chunk| RGBA8 {
            r: chunk[0],
            g: chunk[1],
            b: chunk[2],
            a: chunk[3],
        })
        .collect();

    let img = Img::new(rgba_pixels, 128,128);
    // let img = load_rgba(cropped_image)?;

    // let img_vec = bytemuck::cast_slice(&img.clone().into_buf());
    // fs::write("./cropped.png", dst_image.buffer())?;
    // let resized_img = load_rgba(dst_image.buffer())?;
    

    let enc = Encoder::new()
        .with_quality(quality)
        .with_depth(depth)
        .with_speed(speed)
        .with_alpha_quality(alpha_quality)
        .with_internal_color_space(color_space)
        .with_alpha_color_mode(AlphaColorMode::UnassociatedClean)
        .with_num_threads(None);

    let EncodedImage { avif_file, color_byte_size, alpha_byte_size , .. } = enc.encode_rgba(img.as_ref())?;
    
    Ok(avif_file)
}

fn load_rgba(data: Vec<u8>) -> anyhow::Result<ImgVec<RGBA8>> {
    use rgb::prelude::*;

    let img = load_image::load_data(&data)?.into_imgvec();

    let img = match img {
        load_image::export::imgref::ImgVecKind::RGB8(img) => img.map_buf(|buf| buf.into_iter().map(|px| px.with_alpha(255)).collect()),
        load_image::export::imgref::ImgVecKind::RGBA8(img) => img,
        load_image::export::imgref::ImgVecKind::RGB16(img) => img.map_buf(|buf| buf.into_iter().map(|px| px.map(|c| (c >> 8) as u8).with_alpha(255)).collect()),
        load_image::export::imgref::ImgVecKind::RGBA16(img) => img.map_buf(|buf| buf.into_iter().map(|px| px.map(|c| (c >> 8) as u8)).collect()),
        load_image::export::imgref::ImgVecKind::GRAY8(img) => img.map_buf(|buf| buf.into_iter().map(|g| { let c = g.0; RGBA8::new(c,c,c,255) }).collect()),
        load_image::export::imgref::ImgVecKind::GRAY16(img) => img.map_buf(|buf| buf.into_iter().map(|g| { let c = (g.0>>8) as u8; RGBA8::new(c,c,c,255) }).collect()),
        load_image::export::imgref::ImgVecKind::GRAYA8(img) => img.map_buf(|buf| buf.into_iter().map(|g| { let c = g.0; RGBA8::new(c,c,c,g.1) }).collect()),
        load_image::export::imgref::ImgVecKind::GRAYA16(img) => img.map_buf(|buf| buf.into_iter().map(|g| { let c = (g.0>>8) as u8; RGBA8::new(c,c,c,(g.1>>8) as u8) }).collect()),
    };
    
    Ok(img)
}
fn load_rgba_test() -> anyhow::Result<(Vec<RGBA8>, usize, usize)> {
    let image = load_image::load_path("./image.png")?;

    let width = image.width;
    let height = image.height;


    let img = match image.bitmap {
        ImageData::RGBA8(img) => img,
        ImageData::RGB8(img) =>  img.iter().map(|buf| buf.with_alpha(255)).collect(),
        _ => todo!()
    };

    Ok((img,width,height))
}


fn crop_image(image_data: ImgVec<RGBA8>) -> anyhow::Result<Vec<u8>> {

    let width: u32 = image_data.width() as u32;
    let height: u32 = image_data.height() as u32;

    let img_bytes: Vec<u8> = image_data.into_buf().iter().flat_map(|pixel| vec![pixel.r, pixel.g, pixel.b, pixel.a]).collect();

    let src_image = Image::from_vec_u8(
        width,
        height,
        img_bytes,
        PixelType::U8x4, // RGBA8
    )?;


    let mut cropped_image = Image::new(
        128,
        128,
        PixelType::U8x4, // RGBA8
    );

    let mut resizer = Resizer::new();
    resizer.resize(&src_image, &mut cropped_image, &RESIZE_OPTIONS)?;

    // let file = File::create("./cropped.png")?;
    // let ref mut w = BufWriter::new(file);
    // let mut encoder = PngEncoder::new(w, 128u32, 128u32);
    // encoder.set_color(png::ColorType::Rgba);
    // encoder.set_depth(png::BitDepth::Eight);
    //
    // let mut writer = encoder.write_header()?;
    //
    // // Write the image data (RGBA)
    // writer.write_image_data(cropped_image.buffer())?;


    // let (image, width, height) = load_rgba_test().unwrap();
    //
    // let raw_pixels: &[u8] = bytemuck::cast_slice(&image);
    //
    // assert_eq!(raw_pixels.len(), (width * height * 4) as usize, "Invalid buffer size!");
    //

    Ok(cropped_image.into_vec())
}