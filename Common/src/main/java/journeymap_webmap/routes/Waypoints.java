package journeymap_webmap.routes;

import io.javalin.http.ContentType;
import io.javalin.http.Context;
import journeymap.client.texture.TextureCache;

import java.io.IOException;
import java.nio.channels.Channels;

public class Waypoints
{
    public static void iconGet(Context ctx)
    {
        String id = ctx.pathParam("id");

        var img = TextureCache.getColorizedWaypointIcon(id);

        if (img != null)
        {
            var nativeImage = img.getPixels();
            if (nativeImage != null && nativeImage.pixels > 0)
            {
                try (var channel = Channels.newChannel(ctx.outputStream()))
                {
                    ctx.contentType(ContentType.IMAGE_PNG);
                    nativeImage.writeToChannel(channel);
                    ctx.outputStream().flush();
                }
                catch (IOException e)
                {
                    // nothing
                }
            }
        }
    }


}
