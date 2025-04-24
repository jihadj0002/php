<div class="wrap">
    <h1>AI Content Suite</h1>
    
    <div class="ai-tabs">
        <div class="tab active" data-tab="blog">Blog Generator</div>
        <div class="tab" data-tab="images">Image Generator</div>
        <div class="tab" data-tab="videos">Video Generator</div>
        <div class="tab" data-tab="settings">Settings</div>
    </div>
    
    <div class="tab-content active" id="blog-tab">
        <h2>Generate Complete Blog Post</h2>
        <div class="form-group">
            <label for="headlines">Main Headlines/Topics:</label>
            <textarea id="headlines" rows="3"></textarea>
        </div>
        
        <div class="form-group">
            <label for="tone">Writing Tone:</label>
            <select id="tone">
                <option value="professional">Professional</option>
                <option value="casual">Casual</option>
                <option value="friendly">Friendly</option>
                <option value="humorous">Humorous</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="length">Approximate Word Count:</label>
            <input type="number" id="length" value="800" min="300" max="2000">
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" id="include-images"> Include AI-generated images
            </label>
            <label>
                <input type="checkbox" id="include-seo"> Include SEO optimization
            </label>
        </div>
        
        <button id="generate-blog" class="button button-primary">Generate Complete Blog Post</button>
        
        <div id="generation-results" class="hidden">
            <h3>Generated Content</h3>
            <div id="generated-text"></div>
            <div id="generated-media"></div>
            <button id="publish-post" class="button">Publish to WordPress</button>
        </div>
    </div>
    
    <!-- Other tabs would go here -->
</div>