"use strict";

// InstagramUnfollowers — paste into the browser console while on https://www.instagram.com
//
// Fixes applied to the original script:
//  - INSTAGRAM_HOSTNAME was mangled into a markdown link ("[www.instagram.com](https://www.instagram.com)"),
//    so init() always alerted "Can be used only on Instagram routes" and never rendered the overlay.
//  - User IDs are now consistently handled as strings. Before, "select all" stored raw ids while
//    the checkbox renderer compared against parseInt(id), so re-renders during a scan lost the checks.
//  - A failed page fetch retried immediately in a tight loop with no delay or limit.
//  - Unfollow requests that returned a non-2xx status were reported as successful.
//  - Profile links were relative to the current path and broke when not on the root page.
//  - <span class='nonfollower-count' /> is not a valid self-closing element in HTML.
//  - Results list is built as a single string instead of appending to innerHTML per row.

const INSTAGRAM_HOSTNAME = "www.instagram.com";
const MAX_FETCH_RETRIES = 5;

let nonFollowersList = [];
let userIdsToUnfollow = [];
let isActiveProcess = false;

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function getCookie(name) {
    const parts = `; ${document.cookie}`.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(";").shift();
}

function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

function followingUrlGenerator(after) {
    const variables = {
        id: getCookie("ds_user_id"),
        include_reel: "true",
        fetch_mutual: "false",
        first: "24",
    };
    if (after) variables.after = after;
    return `https://www.instagram.com/graphql/query/?query_hash=3dec7e2c57367ef3da3d987d89f9dbc8&variables=${encodeURIComponent(JSON.stringify(variables))}`;
}

function unfollowUserUrlGenerator(userId) {
    return `https://www.instagram.com/web/friendships/${userId}/unfollow/`;
}

function getElementByClass(selector) {
    const el = document.querySelector(selector);
    if (el === null) throw new Error(`Unable to find element by class: ${selector}`);
    return el;
}

function getUserById(id) {
    const user = nonFollowersList.find((u) => String(u.id) === String(id));
    if (user === undefined) console.error(`Unable to find user by id: ${id}`);
    return user;
}

function sortByUsername(list) {
    return [...list].sort((a, b) => a.username.localeCompare(b.username));
}

function copyListToClipboard() {
    const text = sortByUsername(nonFollowersList).map((u) => u.username).join("\n");
    copyToClipboard(text);
}

async function copyToClipboard(text) {
    await navigator.clipboard.writeText(text);
    alert("List copied to clipboard!");
}

function onToggleUser() {
    getElementByClass(".selected-user-count").textContent = `[${userIdsToUnfollow.length}]`;
}

function toggleUser(id) {
    id = String(id);
    userIdsToUnfollow = userIdsToUnfollow.includes(id)
        ? userIdsToUnfollow.filter((u) => u !== id)
        : [...userIdsToUnfollow, id];
    onToggleUser();
}

function toggleAllUsers(checked = false) {
    document.querySelectorAll(".account-checkbox").forEach((cb) => (cb.checked = checked));
    userIdsToUnfollow = checked ? nonFollowersList.map((u) => String(u.id)) : [];
    onToggleUser();
}

function renderResults(list) {
    const sorted = sortByUsername(list);
    getElementByClass(".toggle-all-checkbox").disabled = false;
    const container = getElementByClass(".results-container");

    let currentLetter = "";
    let html = "";
    sorted.forEach((user) => {
        const id = String(user.id);
        const isChecked = userIdsToUnfollow.includes(id);
        const letter = user.username.substring(0, 1).toUpperCase();
        if (currentLetter !== letter) {
            currentLetter = letter;
            html += `<div class='alphabet-character'>${escapeHtml(letter)}</div>`;
        }
        const username = escapeHtml(user.username);
        html += `<label class='result-item'>
            <div class='flex grow align-center'>
                <img class='avatar' src='${escapeHtml(user.profile_pic_url)}' />&nbsp;&nbsp;&nbsp;&nbsp;
                <div class='flex column'>
                    <a class='fs-xlarge' target='_blank' href='/${username}/'>${username}</a>
                    <span class='fs-medium'>${escapeHtml(user.full_name)}</span>
                </div>
                ${user.is_verified ? "&nbsp;&nbsp;&nbsp;<div class='verified-badge'>✔</div>" : ""}
                ${user.is_private ? "<div class='flex justify-center w-100'><span class='private-indicator'>Private</span></div>" : ""}
            </div>
            <input
                class='account-checkbox'
                type='checkbox'
                onchange='toggleUser("${id}")'
                ${isChecked ? "checked" : ""} />
        </label>`;
    });
    container.innerHTML = html;
}

async function run(includeVerified) {
    getElementByClass(".run-scan").remove();
    getElementByClass(".include-verified-checkbox").disabled = true;
    nonFollowersList = await getNonFollowersList(includeVerified);
    getElementByClass(".copy-list").disabled = false;
}

function renderOverlay() {
    let includeVerified = true;
    document.body.innerHTML = `
        <main class='iu'>
            <div class='overlay'>
                <header class='top-bar'>
                    <div class='logo' onclick='location.reload()'>InstagramUnfollowers</div>
                    <label class='flex align-center'>
                        <input type='checkbox' class='include-verified-checkbox' ${includeVerified ? "checked" : ""} /> Include verified
                    </label>
                    <button class='copy-list' onclick='copyListToClipboard()' disabled>COPY LIST</button>
                    <button class='fs-large clr-red' onclick='unfollow()'>UNFOLLOW <span class='selected-user-count'>[0]</span></button>
                    <input type='checkbox' class='toggle-all-checkbox' onclick='toggleAllUsers(this.checked)' disabled />
                </header>

                <button class='run-scan'>RUN</button>
                <div class='results-container'></div>

                <footer class='bottom-bar'>
                    <div>Non-followers: <span class='nonfollower-count'>0</span></div>
                    <div class='sleeping-text'></div>
                    <div class='progressbar-container'>
                        <div class='progressbar-bar'></div>
                        <span class='progressbar-text'>0%</span>
                    </div>
                </footer>
            </div>
        </main>`;
    getElementByClass(".run-scan").addEventListener("click", () => run(includeVerified));
    getElementByClass(".include-verified-checkbox").addEventListener("change", () => (includeVerified = !includeVerified));
}

async function getNonFollowersList(includeVerified = true) {
    if (isActiveProcess) return;

    const nonFollowers = [];
    let hasNextPage = true;
    let scrollCycle = 0;
    let processedCount = 0;
    let totalCount = -1;
    let retries = 0;
    isActiveProcess = true;

    let url = followingUrlGenerator();
    const progressBar = getElementByClass(".progressbar-bar");
    const progressText = getElementByClass(".progressbar-text");
    const countEl = getElementByClass(".nonfollower-count");
    const sleepingText = getElementByClass(".sleeping-text");

    while (hasNextPage) {
        let response;
        try {
            const res = await fetch(url, { credentials: "include" });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            response = await res.json();
        } catch (err) {
            console.error(err);
            retries += 1;
            if (retries > MAX_FETCH_RETRIES) {
                alert("Failed to fetch the following list too many times. Instagram may be rate-limiting you — try again later.");
                break;
            }
            sleepingText.style.display = "block";
            sleepingText.innerHTML = `Request failed, retrying (${retries}/${MAX_FETCH_RETRIES})...`;
            await sleep(5000 * retries);
            sleepingText.style.display = "none";
            continue;
        }
        retries = 0;

        const edgeFollow = response?.data?.user?.edge_follow;
        if (!edgeFollow) {
            console.error("Unexpected response shape", response);
            alert("Unexpected response from Instagram. The API this script relies on may have changed.");
            break;
        }

        if (totalCount === -1) totalCount = edgeFollow.count;
        hasNextPage = edgeFollow.page_info.has_next_page;
        url = followingUrlGenerator(edgeFollow.page_info.end_cursor);
        processedCount += edgeFollow.edges.length;

        edgeFollow.edges.forEach((edge) => {
            const node = edge.node;
            if (!includeVerified && node.is_verified) return;
            if (!node.follows_viewer) nonFollowers.push(node);
        });

        const percentage = totalCount > 0 ? `${Math.min(100, Math.ceil((processedCount / totalCount) * 100))}%` : "0%";
        progressText.innerHTML = percentage;
        progressBar.style.width = percentage;
        countEl.textContent = String(nonFollowers.length);
        renderResults(nonFollowers);

        await sleep(Math.floor(Math.random() * 400) + 1000);

        scrollCycle += 1;
        if (scrollCycle > 6) {
            scrollCycle = 0;
            sleepingText.style.display = "block";
            sleepingText.innerHTML = "Sleeping 10 secs to prevent getting temp blocked...";
            await sleep(10000);
        }
        sleepingText.style.display = "none";
    }

    progressBar.style.backgroundColor = "#59A942";
    progressText.innerHTML = "DONE";
    isActiveProcess = false;
    return nonFollowers;
}

async function unfollow() {
    if (isActiveProcess) return;
    if (userIdsToUnfollow.length === 0) {
        alert("Must select at least a single user to unfollow");
        return;
    }
    if (!confirm("Are you sure?")) return;

    const csrftoken = getCookie("csrftoken");
    if (csrftoken === undefined) throw new Error("csrftoken cookie is undefined");

    const sleepingText = getElementByClass(".sleeping-text");
    const progressBar = getElementByClass(".progressbar-bar");
    const progressText = getElementByClass(".progressbar-text");
    getElementByClass(".toggle-all-checkbox").disabled = true;

    const container = getElementByClass(".results-container");
    container.innerHTML = "";
    const scrollToBottom = () => window.scrollTo(0, container.scrollHeight);

    progressText.innerHTML = "0%";
    progressBar.style.width = "0%";
    isActiveProcess = true;

    const ids = [...userIdsToUnfollow];
    let counter = 0;
    for (const id of ids) {
        const user = getUserById(id);
        const username = escapeHtml(user?.username ?? id);
        try {
            const res = await fetch(unfollowUserUrlGenerator(id), {
                headers: {
                    "content-type": "application/x-www-form-urlencoded",
                    "x-csrftoken": csrftoken,
                },
                method: "POST",
                mode: "cors",
                credentials: "include",
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            container.innerHTML += `<div class='p-medium'>Unfollowed
                <a class='clr-inherit' target='_blank' href='/${username}/'> ${username}</a>
                <span class='clr-cyan'> [${counter + 1}/${ids.length}]</span>
            </div>`;
        } catch (err) {
            console.error(err);
            container.innerHTML += `<div class='p-medium clr-red'>Failed to unfollow ${username} [${counter + 1}/${ids.length}]</div>`;
        }

        counter += 1;
        const percentage = `${Math.ceil((counter / ids.length) * 100)}%`;
        progressText.innerHTML = percentage;
        progressBar.style.width = percentage;
        scrollToBottom();

        if (counter === ids.length) break;

        await sleep(Math.floor(Math.random() * 2000) + 4000);
        if (counter % 5 === 0) {
            sleepingText.style.display = "block";
            sleepingText.innerHTML = "Sleeping 5 minutes to prevent getting temp blocked...";
            scrollToBottom();
            await sleep(300000);
        }
        sleepingText.style.display = "none";
    }

    progressBar.style.backgroundColor = "#59A942";
    progressText.innerHTML = "DONE";
    isActiveProcess = false;
    container.innerHTML += "<hr /><div class='fs-large p-medium clr-green'>All DONE!</div><hr />";
    scrollToBottom();
}

function init() {
    if (location.hostname === INSTAGRAM_HOSTNAME) {
        document.title = "InstagramUnfollowers";
        renderOverlay();
    } else {
        alert("Can be used only on Instagram routes");
    }
}

window.addEventListener("beforeunload", (e) => {
    if (isActiveProcess) {
        e.preventDefault();
        e.returnValue = "Changes you made may not be saved.";
        return e.returnValue;
    }
});

init();

const styleMarkup = `
html { background-color: #222 !important; }
html .iu { margin-bottom: 10rem; }
html .iu .overlay { background-color: #222; color: #fff; height: 100%; font-family: system-ui; }
html .iu header.top-bar { position: fixed; top: 0; left: 0; right: 0; display: flex; align-items: center; justify-content: space-between; padding: 1rem; height: 2.5rem; background-color: #333; z-index: 1; }
html .iu header .logo { font-family: monospace; font-size: 1.5em; cursor: pointer; }
html .iu footer.bottom-bar { position: fixed; bottom: 0; left: 0; right: 0; display: flex; align-items: center; justify-content: space-between; padding: 1rem; background-color: #000; font-weight: bold; z-index: 1; }
html .iu label { cursor: pointer; }
html .iu input[type='checkbox'] { height: 1.1rem; width: 1.1rem; }
html .iu a { color: inherit; text-decoration-color: transparent; transition: text-decoration-color 0.1s; }
html .iu a:hover { text-decoration-color: inherit; }
html .iu button { background: none; border: none; padding: 0; cursor: pointer; }
html .iu button.copy-list { color: white; font-size: 1rem; }
html .iu button.run-scan { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); font-size: 2em; color: white; border: 1px solid white; height: 160px; width: 160px; border-radius: 50%; }
html .iu .progressbar-container { width: 175px; height: 30px; border-radius: 5px; position: relative; border: 1px solid #7b7777; }
html .iu .progressbar-bar { width: 0; height: 100%; background-color: #7b7777; }
html .iu .progressbar-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); }
html .iu .sleeping-text { color: yellow; }
html .iu .results-container { transform: translateY(75px); }
html .iu .results-container .alphabet-character { margin: 1rem; padding: 1rem; font-size: 2em; border-bottom: 1px solid #333; }
html .iu .results-container .result-item { display: flex; align-items: center; padding: 1rem; border-radius: 3px; cursor: pointer; }
html .iu .results-container .result-item .avatar { width: 75px; border-radius: 50%; }
html .iu .results-container .result-item .verified-badge { background-color: #49adf4; border-radius: 50%; padding: 0.2rem 0.3rem; font-size: 0.45em; height: fit-content; transform: translateY(-15px); }
html .iu .results-container .result-item .private-indicator { border: 2px solid #51bb42; border-radius: 25px; padding: 0.5rem; color: #51bb42; font-weight: 500; }

/** HELPERS */
.flex { display: flex; }
.flex.align-center { align-items: center; }
.flex.justify-center { justify-content: center; }
.flex.column { flex-direction: column; }
.grow { flex: 1; }
.w-100 { width: 100%; }
.p-small { padding: 0.5rem; }
.p-medium { padding: 1rem; }
.p-large { padding: 1.5rem; }
.p-xlarge { padding: 2rem; }
.fs-small { font-size: 0.5rem; }
.fs-medium { font-size: 1rem; }
.fs-large { font-size: 1.5rem; }
.fs-xlarge { font-size: 2rem; }
.clr-inherit { color: inherit; }
.clr-red { color: #bd2121; }
.clr-green { color: #56d756; }
.clr-cyan { color: #00ffff; }
`;

const elStyle = document.createElement("style");
elStyle.innerHTML = styleMarkup;
document.head.appendChild(elStyle);
