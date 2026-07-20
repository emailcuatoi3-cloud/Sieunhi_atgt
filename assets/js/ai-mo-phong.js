/* ai-mo-phong.js — AI Traffic Simulation Engine (Canvas)
   =========================================================
   Thay thế hoàn toàn hệ thống emoji + CSS animation cũ bằng một
   mô phỏng giao thông thật trên <canvas>:
     - Ngã tư chuẩn: 2 chiều lưu thông, mỗi chiều có làn ô tô riêng
       và làn xe máy/xe đạp riêng, dải phân cách, vạch dừng, vạch
       sang đường, vỉa hè, cây xanh, đèn đường, biển báo, nhà phố.
     - Mỗi xe có trạng thái AI: lane, direction, speed, target,
       currentLane, turningState, trafficRuleState.
     - Xe dừng đúng vạch khi đèn đỏ, giữ khoảng cách an toàn với xe
       phía trước, tăng/giảm tốc mượt (không giật, không dịch chuyển
       tức thời), rẽ trái/phải/đi thẳng theo xác suất tại ngã tư với
       góc xoay luôn khớp hướng di chuyển thực tế.
     - Toàn bộ phương tiện vẽ bằng vector (canvas path), nhìn từ trên
       xuống, có bóng đổ nhẹ — không dùng emoji.
   ========================================================= */
document.addEventListener("DOMContentLoaded", () => {
  /* =====================================================================
     1. THIẾT LẬP CANVAS
     ===================================================================== */
  const boardEl = document.getElementById("board");
  const canvas = document.getElementById("simCanvas");
  const ctx = canvas.getContext("2d");
  let W = 0,
    H = 0,
    CX = 0,
    CY = 0;

  function resizeCanvas() {
    const rect = boardEl.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    W = rect.width;
    H = rect.height;
    CX = W / 2;
    CY = H / 2;
    canvas.width = Math.round(W * dpr);
    canvas.height = Math.round(H * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    computeGeometry();
  }
  window.addEventListener("resize", resizeCanvas);

  /* =====================================================================
     2. HÌNH HỌC NGÃ TƯ (làn ô tô riêng, làn xe máy/xe đạp riêng, dải phân cách)
     ===================================================================== */
  const LANE_CAR = 32; // bề rộng làn ô tô/xe tải/xe buýt
  const LANE_BIKE = 22; // bề rộng làn xe máy + xe đạp (dùng chung, xe đạp sát mép ngoài)
  const MEDIAN = 6; // dải phân cách giữa 2 chiều
  const SIDEWALK = 46; // bề rộng vỉa hè mỗi bên
  const CROSSWALK_W = 20; // bề rộng dải vạch qua đường (đặt sát mép giao lộ)
  const STOP_GAP = 10; // khoảng cách từ vạch dừng xe tới mép ngoài vạch qua đường
  let geo = {};

  function computeGeometry() {
    const halfMedian = MEDIAN / 2;
    const carC = halfMedian + LANE_CAR / 2; // tâm làn ô tô cách tâm đường
    const bikeC = halfMedian + LANE_CAR + LANE_BIKE / 2; // tâm làn xe máy/xe đạp
    const roadHalf = halfMedian + LANE_CAR + LANE_BIKE; // nửa bề rộng cả cụm đường 1 chiều
    const edgeGap = CROSSWALK_W + STOP_GAP; // khoảng cách từ mép giao lộ tới vạch dừng xe (đã tính luôn vạch qua đường)
    geo = {
      roadHalf,
      // Ngang (đường chạy trái-phải): dương = xuống dưới màn hình
      hEastCar: CY + carC,
      hEastBike: CY + bikeC, // chiều Đông (trái->phải)
      hWestCar: CY - carC,
      hWestBike: CY - bikeC, // chiều Tây (phải->trái)
      // Dọc (đường chạy trên-dưới): dương = sang phải màn hình
      vSouthCar: CX - carC,
      vSouthBike: CX - bikeC, // chiều Nam (trên->dưới) = làn trái
      vNorthCar: CX + carC,
      vNorthBike: CX + bikeC, // chiều Bắc (dưới->trên) = làn phải
      // Vạch dừng xe = vị trí ĐẦU XE khi dừng hẳn — luôn nằm TRƯỚC (ngoài) vạch qua đường,
      // không phải tâm xe, để xe không bao giờ đè lên vạch qua đường hay lấn vào giao lộ.
      stopN: CY - roadHalf - edgeGap, // vạch dừng phía Bắc (xe đi xuống/south dừng ở đây)
      stopS: CY + roadHalf + edgeGap, // vạch dừng phía Nam (xe đi lên/north dừng ở đây)
      stopE: CX + roadHalf + edgeGap, // vạch dừng phía Đông (xe đi sang trái/west dừng ở đây)
      stopW: CX - roadHalf - edgeGap, // vạch dừng phía Tây (xe đi sang phải/east dừng ở đây)
      boxL: CX - roadHalf,
      boxR: CX + roadHalf,
      boxT: CY - roadHalf,
      boxB: CY + roadHalf,
    };
  }

  /* =====================================================================
     3. LOẠI PHƯƠNG TIỆN — tốc độ, kích thước, làn phù hợp
     ===================================================================== */
  const VEHICLE_TYPES = {
    bicycle: {
      w: 15,
      h: 7,
      maxSpeed: 34,
      accel: 26,
      laneType: "bike",
      priority: false,
    },
    motorbike: {
      w: 17,
      h: 8,
      maxSpeed: 58,
      accel: 42,
      laneType: "bike",
      priority: false,
    },
    car: {
      w: 30,
      h: 15,
      maxSpeed: 52,
      accel: 30,
      laneType: "car",
      priority: false,
    },
    truck: {
      w: 42,
      h: 17,
      maxSpeed: 34,
      accel: 16,
      laneType: "car",
      priority: false,
    },
    bus: {
      w: 46,
      h: 18,
      maxSpeed: 36,
      accel: 15,
      laneType: "car",
      priority: false,
    },
    ambulance: {
      w: 32,
      h: 16,
      maxSpeed: 70,
      accel: 40,
      laneType: "car",
      priority: true,
    },
  };

  /* Trộn loại xe theo lượt chơi (do newScenario() trong phần game gán vào) */
  let vehicleBias = ["car", "motorbike", "bicycle"];

  function weightedVehicleType(laneType) {
    const pool = Object.keys(VEHICLE_TYPES).filter(
      (k) => VEHICLE_TYPES[k].laneType === laneType,
    );
    // Ưu tiên loại xe có trong "bias" của lượt chơi hiện tại, thỉnh thoảng random loại khác cho sinh động
    const biasedPool = pool.filter((k) => vehicleBias.includes(k));
    const usePool =
      biasedPool.length && Math.random() < 0.7 ? biasedPool : pool;
    return usePool[Math.floor(Math.random() * usePool.length)];
  }

  /* =====================================================================
     4. TRẠNG THÁI MÔ PHỎNG
     ===================================================================== */
  let vehicles = []; // danh sách xe đang chạy
  let intersectionLock = null; // id xe đang rẽ trong giao lộ — chỉ 1 xe được rẽ cùng lúc, tránh chồng chéo
  let vehIdSeq = 0;
  let signalState = "red"; // trạng thái đèn HIỆN TẠI của mô phỏng nền (độc lập với đèn người đi bộ trong game)
  let weatherKey = "sunny";
  let running = true;
  let lastTs = 0;
  let spawnTimers = {}; // hẹn giờ sinh xe cho từng làn
  let ambulanceBlink = 0;

  const DIRS = {
    east: { dx: 1, dy: 0 },
    west: { dx: -1, dy: 0 },
    south: { dx: 0, dy: 1 },
    north: { dx: 0, dy: -1 },
  };

  /* Làn xuất phát: toạ độ cố định theo trục vuông góc + điểm bắt đầu/kết thúc off-screen */
  function laneDefs() {
    return [
      {
        dir: "east",
        laneType: "car",
        y: geo.hEastCar,
        stop: geo.stopW,
      },
      {
        dir: "east",
        laneType: "bike",
        y: geo.hEastBike,
        stop: geo.stopW,
      },
      {
        dir: "west",
        laneType: "car",
        y: geo.hWestCar,
        stop: geo.stopE,
      },
      {
        dir: "west",
        laneType: "bike",
        y: geo.hWestBike,
        stop: geo.stopE,
      },
      {
        dir: "south",
        laneType: "car",
        x: geo.vSouthCar,
        stop: geo.stopN,
      },
      {
        dir: "south",
        laneType: "bike",
        x: geo.vSouthBike,
        stop: geo.stopN,
      },
      {
        dir: "north",
        laneType: "car",
        x: geo.vNorthCar,
        stop: geo.stopS,
      },
      {
        dir: "north",
        laneType: "bike",
        x: geo.vNorthBike,
        stop: geo.stopS,
      },
    ];
  }

  /* =====================================================================
     5. SINH XE
     ===================================================================== */
  function spawnVehicle(lane) {
    const type = weightedVehicleType(lane.laneType);
    const spec = VEHICLE_TYPES[type];
    const d = DIRS[lane.dir];
    let x, y;
    if (lane.dir === "east") {
      x = -40;
      y = lane.y;
    } else if (lane.dir === "west") {
      x = W + 40;
      y = lane.y;
    } else if (lane.dir === "south") {
      x = lane.x;
      y = -40;
    } else {
      x = lane.x;
      y = H + 40;
    }

    // Không sinh xe nếu quá gần xe liền trước cùng làn (tránh chồng xe lúc mới sinh)
    const tooClose = vehicles.some(
      (v) =>
        v.dir === lane.dir &&
        v.laneType === lane.laneType &&
        Math.hypot(v.x - x, v.y - y) < 70,
    );
    if (tooClose) return;

    vehicles.push({
      id: vehIdSeq++,
      type,
      spec,
      dir: lane.dir,
      laneType: lane.laneType,
      fixedCoord: lane.dir === "east" || lane.dir === "west" ? lane.y : lane.x,
      stopAt: lane.stop,
      x,
      y,
      angle: Math.atan2(d.dy, d.dx),
      speed: spec.maxSpeed * 0.6,
      state: "approach", // approach -> waiting -> crossing -> turning -> exit
      turnPlan: null, // 'straight' | 'left' | 'right' (quyết định khi tới gần vạch dừng)
      turnProgress: 0,
      turnPath: null,
    });
  }

  const MAX_VEHICLES = 14; // giới hạn tổng số xe cùng lúc, tránh dồn ứ quá tải màn hình

  function updateSpawners(dt) {
    laneDefs().forEach((lane) => {
      const key = lane.dir + "-" + lane.laneType;
      spawnTimers[key] = (spawnTimers[key] || 0) - dt;
      if (spawnTimers[key] <= 0) {
        if (vehicles.length < MAX_VEHICLES) spawnVehicle(lane);
        const base = lane.laneType === "bike" ? 2.2 : 3.2;
        spawnTimers[key] = base + Math.random() * 2.4;
      }
    });
  }

  /* =====================================================================
     6. LOGIC ĐÈN TÍN HIỆU cho xe (độc lập, không phụ thuộc đèn người đi bộ)
     Trục ngang (đông-tây) và trục dọc (nam-bắc) luân phiên xanh/đỏ.
     ===================================================================== */
  let axisPhase = "h-green"; // 'h-green' | 'h-yellow' | 'v-green' | 'v-yellow'
  let axisTimer = 6;
  function updateAxisSignal(dt) {
    axisTimer -= dt;
    if (axisTimer > 0) return;
    if (axisPhase === "h-green") {
      axisPhase = "h-yellow";
      axisTimer = 1.4;
    } else if (axisPhase === "h-yellow") {
      axisPhase = "v-green";
      axisTimer = 6;
    } else if (axisPhase === "v-green") {
      axisPhase = "v-yellow";
      axisTimer = 1.4;
    } else {
      axisPhase = "h-green";
      axisTimer = 6;
    }
  }
  /** Có phải xe theo hướng `dir` được phép đi thẳng qua ngã tư lúc này? */
  function isGoAxis(dir) {
    const horizontal = dir === "east" || dir === "west";
    if (horizontal) return axisPhase === "h-green";
    return axisPhase === "v-green";
  }
  function isCautionAxis(dir) {
    const horizontal = dir === "east" || dir === "west";
    if (horizontal) return axisPhase === "h-yellow";
    return axisPhase === "v-yellow";
  }

  /**
   * Tín hiệu dành cho người đi bộ tại đúng vạch kẻ đường cậu bé đang đứng
   * (băng qua ĐƯỜNG NGANG) — an toàn (xanh) chính xác khi trục ngang đang đỏ
   * (xe ngang đã dừng hẳn), không phải một con số ngẫu nhiên tách biệt.
   */
  function getPedestrianSignal() {
    if (axisPhase === "v-green") return "green"; // trục ngang đang đỏ -> người đi bộ an toàn
    if (axisPhase === "v-yellow") return "yellow"; // trục ngang sắp xanh trở lại -> chuẩn bị dừng
    return "red"; // h-green / h-yellow -> xe ngang đang chạy, chưa an toàn
  }

  /* =====================================================================
     7. CẬP NHẬT CHUYỂN ĐỘNG (AI mỗi xe: bám làn, giữ khoảng cách, dừng đèn đỏ, rẽ)
     ===================================================================== */
  function distAlong(v) {
    // khoảng cách đã đi được tính theo trục chuyển động, dùng để so sánh thứ tự xe trong làn
    if (v.dir === "east") return v.x;
    if (v.dir === "west") return -v.x;
    if (v.dir === "south") return v.y;
    return -v.y;
  }

  function vehicleAhead(v) {
    let nearest = null,
      nearestGap = Infinity;
    vehicles.forEach((o) => {
      if (o.id === v.id || o.dir !== v.dir || o.laneType !== v.laneType) return;
      const gap = distAlong(o) - distAlong(v);
      if (gap > 0 && gap < nearestGap) {
        nearestGap = gap;
        nearest = o;
      }
    });
    return { vehicle: nearest, gap: nearestGap };
  }

  function updateVehicle(v, dt) {
    const spec = v.spec;

    if (v.state === "turning") {
      updateTurning(v, dt);
      return;
    }
    if (v.state === "exit") return;

    // --- Quyết định tốc độ mục tiêu ---
    let targetSpeed = spec.maxSpeed;

    // Giữ khoảng cách an toàn với xe phía trước cùng làn
    const { vehicle: ahead, gap } = vehicleAhead(v);
    const safeGap = 26 + spec.w * 0.9;
    if (ahead) {
      if (gap < safeGap)
        targetSpeed = Math.min(targetSpeed, Math.max(0, ahead.speed - 4));
      else if (gap < safeGap * 2)
        targetSpeed = Math.min(targetSpeed, ahead.speed + 6);
    }

    // Kiểm tra đèn tín hiệu khi còn cách vạch dừng (xe cứu thương được ưu tiên, bỏ qua đèn đỏ)
    // Trừ nửa chiều dài xe (spec.w/2) để tính theo ĐẦU XE, không phải tâm xe — đảm bảo xe dài
    // (xe tải/buýt) không bao giờ lấn qua vạch dừng hay đè lên vạch qua đường.
    const distToStop =
      (v.dir === "east"
        ? v.stopAt - v.x
        : v.dir === "west"
          ? v.x - v.stopAt
          : v.dir === "south"
            ? v.stopAt - v.y
            : v.y - v.stopAt) -
      spec.w / 2;

    const mustStop =
      !spec.priority &&
      !isGoAxis(v.dir) &&
      !(isCautionAxis(v.dir) && distToStop < 12);
    if (mustStop && distToStop < 90 && distToStop > -6) {
      // Giảm tốc dần để dừng đúng vạch, không phanh gấp/giật
      const brake = Math.max(0, Math.min(spec.maxSpeed, distToStop * 1.3));
      targetSpeed = Math.min(targetSpeed, brake);
      v.state = distToStop < 6 ? "waiting" : "approach";
    } else if (v.state === "waiting") {
      v.state = "approach";
    }

    // Tăng/giảm tốc mượt (không teleport, không giật)
    const rate = targetSpeed > v.speed ? spec.accel : spec.accel * 1.6;
    v.speed +=
      Math.sign(targetSpeed - v.speed) *
      Math.min(Math.abs(targetSpeed - v.speed), rate * dt);
    v.speed = Math.max(0, v.speed);

    // Đã qua vạch dừng và chưa có kế hoạch rẽ -> AI quyết định rẽ trái/phải/đi thẳng
    if (
      v.turnPlan === null &&
      distToStop <= 8 &&
      (isGoAxis(v.dir) || spec.priority)
    ) {
      const r = Math.random();
      let plan = r < 0.62 ? "straight" : r < 0.81 ? "left" : "right";
      // Giao lộ đang có xe khác rẽ -> đi thẳng để tránh 2 xe chồng chéo lên nhau giữa ngã tư
      if (plan !== "straight" && intersectionLock !== null) plan = "straight";
      v.turnPlan = plan;
      if (v.turnPlan !== "straight") {
        prepareTurn(v);
        v.state = "turning";
        v.turnProgress = 0;
        intersectionLock = v.id; // khoá giao lộ cho tới khi xe này rẽ xong
        return; // chuyển thẳng sang xử lý khúc cua ngay, tránh trễ nhịp gây dồn xe ở tâm ngã tư
      }
    }

    // Duy chuyển theo hướng hiện tại
    const d = DIRS[v.dir];
    v.x += d.dx * v.speed * dt;
    v.y += d.dy * v.speed * dt;

    // Ra khỏi màn hình -> đánh dấu để dọn dẹp
    if (v.x < -80 || v.x > W + 80 || v.y < -80 || v.y > H + 80)
      v.state = "exit";
  }

  /** Chuẩn bị đường rẽ (3 điểm: vào khúc cua - tâm cua - ra khúc cua) khi xe quyết định rẽ trái/phải */
  function prepareTurn(v) {
    const enter = { x: v.x, y: v.y };
    let newDir, exit, control;
    const r = geo.roadHalf * 0.55;

    // Bảng tra hướng mới khi rẽ trái/phải theo từng hướng đang đi tới
    const table = {
      east: { left: "north", right: "south" },
      west: { left: "south", right: "north" },
      south: { left: "east", right: "west" },
      north: { left: "west", right: "east" },
    };
    newDir = table[v.dir][v.turnPlan];

    const laneCoord =
      v.laneType === "car"
        ? {
            east: geo.hEastCar,
            west: geo.hWestCar,
            south: geo.vSouthCar,
            north: geo.vNorthCar,
          }
        : {
            east: geo.hEastBike,
            west: geo.hWestBike,
            south: geo.vSouthBike,
            north: geo.vNorthBike,
          };

    if (newDir === "east" || newDir === "west") {
      exit = {
        x: newDir === "east" ? geo.boxR + 20 : geo.boxL - 20,
        y: laneCoord[newDir],
      };
    } else {
      exit = {
        x: laneCoord[newDir],
        y: newDir === "south" ? geo.boxB + 20 : geo.boxT - 20,
      };
    }
    // Điểm điều khiển Bézier = điểm "góc cua" thật (nơi 2 đường thẳng nối dài từ hướng vào và
    // hướng ra sẽ gặp nhau) — KHÔNG phải trung điểm, để đường cong bo tròn đúng góc thay vì
    // cắt chéo thẳng qua tâm ngã tư.
    const enteringHorizontal = v.dir === "east" || v.dir === "west";
    control = enteringHorizontal
      ? { x: exit.x, y: enter.y }
      : { x: enter.x, y: exit.y };

    v.turnNewDir = newDir;
    v.turnPath = [enter, control, exit];
  }

  function updateTurning(v, dt) {
    const spec = v.spec;
    const targetSpeed = spec.maxSpeed * 0.55; // vào cua chạy chậm lại cho an toàn/mượt
    v.speed +=
      Math.sign(targetSpeed - v.speed) *
      Math.min(Math.abs(targetSpeed - v.speed), spec.accel * dt);

    const pathLen =
      Math.hypot(
        v.turnPath[2].x - v.turnPath[0].x,
        v.turnPath[2].y - v.turnPath[0].y,
      ) *
        1.3 +
      40;
    v.turnProgress += (v.speed * dt) / pathLen;

    if (v.turnProgress >= 1) {
      // Hoàn tất khúc cua -> đổi hướng chính thức, tiếp tục chạy thẳng trên đường mới
      v.dir = v.turnNewDir;
      v.turnPlan = "straight"; // đã rẽ xong, không rẽ tiếp
      v.state = "approach";
      const p = v.turnPath[2];
      v.x = p.x;
      v.y = p.y;
      const d = DIRS[v.dir];
      v.angle = Math.atan2(d.dy, d.dx);
      // Cập nhật lại toạ độ cố định + vạch dừng tương ứng hướng mới (để không bị tính lại đèn tín hiệu sai)
      v.stopAt = 999999 * (d.dx + d.dy > 0 ? 1 : -1);
      if (intersectionLock === v.id) intersectionLock = null; // nhả khoá giao lộ cho xe khác
      return;
    }

    const [p0, p1, p2] = v.turnPath;
    const t = v.turnProgress;
    const x = (1 - t) * (1 - t) * p0.x + 2 * (1 - t) * t * p1.x + t * t * p2.x;
    const y = (1 - t) * (1 - t) * p0.y + 2 * (1 - t) * t * p1.y + t * t * p2.y;
    const dx = 2 * (1 - t) * (p1.x - p0.x) + 2 * t * (p2.x - p1.x);
    const dy = 2 * (1 - t) * (p1.y - p0.y) + 2 * t * (p2.y - p1.y);
    v.angle = Math.atan2(dy, dx); // góc xoay LUÔN khớp hướng di chuyển tức thời trên đường cong
    v.x = x;
    v.y = y;
  }

  /* =====================================================================
     8. VẼ MÔI TRƯỜNG (đường, vỉa hè, cây xanh, đèn đường, biển báo, nhà phố)
     ===================================================================== */
  function drawEnvironment() {
    ctx.clearRect(0, 0, W, H);

    // Nền cỏ/khu dân cư 4 góc
    ctx.fillStyle = "#1b2a1f";
    ctx.fillRect(0, 0, W, H);

    // Vỉa hè (dải xám bao quanh cụm đường)
    ctx.fillStyle = "#3a3f52";
    ctx.fillRect(0, geo.boxT - SIDEWALK, W, geo.boxB - geo.boxT + SIDEWALK * 2);
    ctx.fillRect(geo.boxL - SIDEWALK, 0, geo.boxR - geo.boxL + SIDEWALK * 2, H);

    // Mặt đường nhựa
    ctx.fillStyle = "#26293b";
    ctx.fillRect(0, geo.boxT, W, geo.boxB - geo.boxT);
    ctx.fillRect(geo.boxL, 0, geo.boxR - geo.boxL, H);

    drawLaneMarkings();
    drawMedians();
    drawCrosswalks();
    drawStopLines();
    drawStreetDecor();
    drawSigns();
    drawTrafficLightPole();
  }

  function drawLaneMarkings() {
    ctx.strokeStyle = "rgba(232,236,251,0.55)";
    ctx.lineWidth = 2;
    ctx.setLineDash([12, 10]);
    // Vạch giữa làn ô tô/xe máy mỗi chiều (ngang)
    [geo.hEastCar + LANE_CAR / 2, geo.hWestCar - LANE_CAR / 2].forEach((y) => {
      ctx.beginPath();
      ctx.moveTo(0, y);
      ctx.lineTo(geo.boxL, y);
      ctx.stroke();
      ctx.beginPath();
      ctx.moveTo(geo.boxR, y);
      ctx.lineTo(W, y);
      ctx.stroke();
    });
    [geo.vSouthCar - LANE_CAR / 2, geo.vNorthCar + LANE_CAR / 2].forEach(
      (x) => {
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x, geo.boxT);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(x, geo.boxB);
        ctx.lineTo(x, H);
        ctx.stroke();
      },
    );
    ctx.setLineDash([]);
  }

  function drawMedians() {
    ctx.fillStyle = "#f4b942";
    ctx.fillRect(0, CY - MEDIAN / 2, geo.boxL, MEDIAN);
    ctx.fillRect(geo.boxR, CY - MEDIAN / 2, W - geo.boxR, MEDIAN);
    ctx.fillRect(CX - MEDIAN / 2, 0, MEDIAN, geo.boxT);
    ctx.fillRect(CX - MEDIAN / 2, geo.boxB, MEDIAN, H - geo.boxB);
  }

  function drawCrosswalks() {
    ctx.fillStyle = "rgba(232,236,251,0.85)";
    const stripe = 7,
      gap = 8;
    // Trên & dưới (băng ngang tại 2 mép dọc của ô giao nhau) — đúng ngay mép giao lộ
    [geo.boxT - CROSSWALK_W, geo.boxB].forEach((topY) => {
      for (let x = geo.boxL + 4; x < geo.boxR - 4; x += stripe + gap) {
        ctx.fillRect(x, topY, stripe, CROSSWALK_W);
      }
    });
    [geo.boxL - CROSSWALK_W, geo.boxR].forEach((leftX) => {
      for (let y = geo.boxT + 4; y < geo.boxB - 4; y += stripe + gap) {
        ctx.fillRect(leftX, y, CROSSWALK_W, stripe);
      }
    });
  }

  function drawStopLines() {
    ctx.fillStyle = "rgba(255,255,255,0.9)";
    // Đông (xe từ Tây tới, chạy sang phải): dừng TRƯỚC vạch qua đường, nửa làn dưới (chiều Đông)
    ctx.fillRect(geo.stopW - 2, CY, 4, geo.roadHalf);
    // Tây (xe từ Đông tới, chạy sang trái): dừng TRƯỚC vạch qua đường, nửa làn trên (chiều Tây)
    ctx.fillRect(geo.stopE - 2, CY - geo.roadHalf, 4, geo.roadHalf);
    // Nam (xe từ Bắc tới, chạy xuống): dừng TRƯỚC vạch qua đường, nửa làn trái (chiều Nam)
    ctx.fillRect(geo.boxL, geo.stopN - 2, geo.roadHalf, 4);
    // Bắc (xe từ Nam tới, chạy lên): dừng TRƯỚC vạch qua đường, nửa làn phải (chiều Bắc)
    ctx.fillRect(CX, geo.stopS - 2, geo.roadHalf, 4);
  }

  function drawStreetDecor() {
    // Cây xanh ở 4 góc vỉa hè
    const spots = [
      [geo.boxL - SIDEWALK * 0.6, geo.boxT - SIDEWALK * 0.6],
      [geo.boxR + SIDEWALK * 0.6, geo.boxT - SIDEWALK * 0.6],
      [geo.boxL - SIDEWALK * 0.6, geo.boxB + SIDEWALK * 0.6],
      [geo.boxR + SIDEWALK * 0.6, geo.boxB + SIDEWALK * 0.6],
    ];
    spots.forEach(([x, y], i) => {
      drawTree(x, y);
      if (i % 2 === 0) drawStreetlight(x + 26, y - 4);
      else drawBuilding(x - 30, y - 10, i);
    });

    // Ghế đá + thùng rác gần vỉa hè trên
    drawBench(geo.boxL + 30, geo.boxT - SIDEWALK + 12);
    drawTrashBin(geo.boxR - 24, geo.boxT - SIDEWALK + 14);
  }

  function drawTree(x, y) {
    ctx.fillStyle = "#5b3a22";
    ctx.fillRect(x - 2, y, 4, 10);
    ctx.fillStyle = "#2f7a44";
    ctx.beginPath();
    ctx.arc(x, y - 4, 11, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = "#3d9856";
    ctx.beginPath();
    ctx.arc(x - 4, y - 8, 7, 0, Math.PI * 2);
    ctx.fill();
  }

  function drawStreetlight(x, y) {
    ctx.strokeStyle = "#8a8fa8";
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(x, y + 22);
    ctx.lineTo(x, y);
    ctx.stroke();
    ctx.fillStyle = "#ffdd88";
    ctx.beginPath();
    ctx.arc(x, y - 3, 3.5, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = "rgba(255,221,136,0.18)";
    ctx.beginPath();
    ctx.arc(x, y - 3, 12, 0, Math.PI * 2);
    ctx.fill();
  }

  function drawBuilding(x, y, seed) {
    const w = 34,
      h = 26;
    ctx.fillStyle = seed % 2 === 0 ? "#4a5170" : "#5a4a52";
    ctx.fillRect(x, y - h, w, h);
    ctx.fillStyle = "#2b2f45";
    ctx.fillRect(x, y - 6, w, 6); // mái/chân nhà
    ctx.fillStyle = "rgba(255,221,136,0.7)";
    for (let r = 0; r < 2; r++)
      for (let c = 0; c < 3; c++) {
        ctx.fillRect(x + 4 + c * 10, y - h + 5 + r * 11, 5, 6);
      }
  }

  function drawBench(x, y) {
    ctx.fillStyle = "#8a6a45";
    ctx.fillRect(x - 10, y, 20, 3);
    ctx.fillRect(x - 10, y - 6, 20, 2);
    ctx.strokeStyle = "#5c4630";
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(x - 8, y + 3);
    ctx.lineTo(x - 8, y + 8);
    ctx.moveTo(x + 8, y + 3);
    ctx.lineTo(x + 8, y + 8);
    ctx.stroke();
  }

  function drawTrashBin(x, y) {
    ctx.fillStyle = "#3d7a5c";
    ctx.fillRect(x - 5, y, 10, 12);
    ctx.fillStyle = "#2c5a44";
    ctx.fillRect(x - 6, y - 2, 12, 3);
  }

  /* Biển báo Việt Nam đơn giản hoá: cấm (tròn viền đỏ), nguy hiểm (tam giác), hiệu lệnh (tròn xanh) */
  function drawSigns() {
    drawSignPost(geo.boxL - 16, geo.boxT - 10, "speed");
    drawSignPost(geo.boxR + 16, geo.boxB + 10, "school");
  }
  function drawSignPost(x, y, kind) {
    ctx.strokeStyle = "#8a8fa8";
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(x, y + 18);
    ctx.lineTo(x, y);
    ctx.stroke();
    if (kind === "speed") {
      ctx.fillStyle = "#fff";
      ctx.beginPath();
      ctx.arc(x, y - 9, 9, 0, Math.PI * 2);
      ctx.fill();
      ctx.strokeStyle = "#e23";
      ctx.lineWidth = 2.5;
      ctx.beginPath();
      ctx.arc(x, y - 9, 9, 0, Math.PI * 2);
      ctx.stroke();
      ctx.fillStyle = "#111";
      ctx.font = "bold 8px sans-serif";
      ctx.textAlign = "center";
      ctx.textBaseline = "middle";
      ctx.fillText("40", x, y - 9);
    } else {
      ctx.fillStyle = "#fff";
      ctx.beginPath();
      ctx.moveTo(x, y - 18);
      ctx.lineTo(x + 9, y);
      ctx.lineTo(x - 9, y);
      ctx.closePath();
      ctx.fill();
      ctx.strokeStyle = "#e23";
      ctx.lineWidth = 2;
      ctx.stroke();
      ctx.fillStyle = "#111";
      ctx.font = "bold 9px sans-serif";
      ctx.textAlign = "center";
      ctx.fillText("🚸", x, y - 4);
    }
  }

  function drawTrafficLightPole() {
    // Cột đèn cho trục NGANG (đông-tây) — đặt ở góc trên-phải ngã tư
    drawSignalHead(
      geo.boxR + 12,
      geo.boxT - 12,
      axisPhase === "h-green"
        ? "green"
        : axisPhase === "h-yellow"
          ? "yellow"
          : "red",
    );
    // Cột đèn cho trục DỌC (nam-bắc) — đặt ở góc dưới-trái ngã tư
    drawSignalHead(
      geo.boxL - 12,
      geo.boxB + 12,
      axisPhase === "v-green"
        ? "green"
        : axisPhase === "v-yellow"
          ? "yellow"
          : "red",
    );
  }

  function drawSignalHead(x, y, activeColor) {
    // Cột đèn dày, cao hơn hẳn để dễ thấy
    ctx.strokeStyle = "#6b7094";
    ctx.lineWidth = 5;
    ctx.beginPath();
    ctx.moveTo(x, y + 10);
    ctx.lineTo(x, y - 38);
    ctx.stroke();

    // Hộp đèn to gấp đôi, có viền sáng để nổi bật trên mọi nền
    ctx.fillStyle = "#0d1030";
    roundRectPath(x - 11, y - 68, 22, 48, 6);
    ctx.fill();
    ctx.strokeStyle = "rgba(255,255,255,0.35)";
    ctx.lineWidth = 2;
    roundRectPath(x - 11, y - 68, 22, 48, 6);
    ctx.stroke();

    const colors = ["red", "yellow", "green"];
    colors.forEach((c, i) => {
      const cy = y - 58 + i * 14;
      const onColor =
        c === "red" ? "#ff3b3b" : c === "yellow" ? "#f4b942" : "#34d399";

      if (c === activeColor) {
        // Vầng sáng lớn, rõ ràng ngay cả khi nhìn từ xa
        ctx.save();
        ctx.globalAlpha = 0.5;
        ctx.fillStyle = onColor;
        ctx.beginPath();
        ctx.arc(x, cy, 13, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
      }

      ctx.fillStyle = c === activeColor ? onColor : "rgba(255,255,255,0.14)";
      ctx.beginPath();
      ctx.arc(x, cy, 5.8, 0, Math.PI * 2);
      ctx.fill();
      if (c === activeColor) {
        ctx.strokeStyle = "#fff";
        ctx.lineWidth = 1.4;
        ctx.stroke();
      }
    });
  }

  /* =====================================================================
     9. VẼ PHƯƠNG TIỆN (vector, có bóng đổ, xoay đúng hướng di chuyển)
     ===================================================================== */
  function drawVehicle(v) {
    ctx.save();
    ctx.translate(v.x, v.y);
    // Bóng đổ nhẹ
    ctx.save();
    ctx.translate(2, 3);
    ctx.rotate(v.angle);
    ctx.fillStyle = "rgba(0,0,0,0.28)";
    roundRectPath(-v.spec.w / 2, -v.spec.h / 2, v.spec.w, v.spec.h, 3);
    ctx.fill();
    ctx.restore();

    ctx.rotate(v.angle);
    drawVehicleBody(v);
    ctx.restore();
  }

  function roundRectPath(x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  const BODY_COLORS = [
    "#e8574c",
    "#3b82f6",
    "#f4b942",
    "#16c765",
    "#8b5cf6",
    "#e8e8ef",
    "#ff8a3d",
  ];
  function colorFor(v) {
    if (!v.color) v.color = BODY_COLORS[v.id % BODY_COLORS.length];
    return v.color;
  }

  function drawVehicleBody(v) {
    const w = v.spec.w,
      h = v.spec.h;
    switch (v.type) {
      case "bicycle": {
        ctx.strokeStyle = "#cfd3e6";
        ctx.lineWidth = 1.4;
        ctx.beginPath();
        ctx.arc(-w * 0.32, 0, h * 0.5, 0, Math.PI * 2);
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(w * 0.32, 0, h * 0.5, 0, Math.PI * 2);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(-w * 0.32, 0);
        ctx.lineTo(w * 0.32, 0);
        ctx.stroke();
        ctx.fillStyle = colorFor(v);
        ctx.beginPath();
        ctx.arc(0, -h * 0.35, h * 0.42, 0, Math.PI * 2);
        ctx.fill(); // người ngồi (từ trên xuống)
        break;
      }
      case "motorbike": {
        ctx.fillStyle = colorFor(v);
        roundRectPath(-w / 2, -h / 2, w, h, 3);
        ctx.fill();
        ctx.fillStyle = "rgba(0,0,0,0.35)";
        ctx.beginPath();
        ctx.arc(-w * 0.1, 0, h * 0.4, 0, Math.PI * 2);
        ctx.fill(); // dáng người lái
        ctx.fillStyle = "#fff59d";
        ctx.beginPath();
        ctx.arc(w / 2 - 1, 0, 1.6, 0, Math.PI * 2);
        ctx.fill(); // đèn trước
        break;
      }
      case "car": {
        ctx.fillStyle = colorFor(v);
        roundRectPath(-w / 2, -h / 2, w, h, 4);
        ctx.fill();
        ctx.fillStyle = "rgba(20,24,40,0.75)";
        roundRectPath(-w * 0.08, -h / 2 + 2, w * 0.42, h - 4, 2);
        ctx.fill(); // kính chắn gió/kính sau
        ctx.fillStyle = "#fff59d";
        ctx.fillRect(w / 2 - 2, -h / 2 + 1.5, 2, 2.4);
        ctx.fillRect(w / 2 - 2, h / 2 - 3.9, 2, 2.4);
        break;
      }
      case "truck": {
        ctx.fillStyle = "#c7cbe0";
        roundRectPath(-w / 2, -h / 2, w * 0.62, h, 2);
        ctx.fill(); // thùng hàng
        ctx.fillStyle = colorFor(v);
        roundRectPath(w * 0.12, -h / 2, w * 0.38, h, 3);
        ctx.fill(); // đầu kéo
        ctx.fillStyle = "rgba(20,24,40,0.7)";
        roundRectPath(w * 0.32, -h / 2 + 2, w * 0.16, h - 4, 2);
        ctx.fill();
        break;
      }
      case "bus": {
        ctx.fillStyle = colorFor(v);
        roundRectPath(-w / 2, -h / 2, w, h, 4);
        ctx.fill();
        ctx.fillStyle = "rgba(20,24,40,0.6)";
        for (let i = 0; i < 4; i++)
          (roundRectPath(
            -w / 2 + 4 + i * (w / 4.4),
            -h / 2 + 2.5,
            w / 4.4 - 3,
            h - 5,
            1.5,
          ),
            ctx.fill());
        break;
      }
      case "ambulance": {
        ctx.fillStyle = "#f4f7ff";
        roundRectPath(-w / 2, -h / 2, w, h, 4);
        ctx.fill();
        ctx.fillStyle = "#e8574c";
        ctx.fillRect(-2, -h / 2 + 2, 4, h - 4);
        ctx.fillRect(-w * 0.12, -1.4, w * 0.28, 2.8);
        // Đèn ưu tiên nhấp nháy đỏ/xanh
        ctx.fillStyle = ambulanceBlink < 0.5 ? "#ff3b3b" : "#3b82ff";
        ctx.beginPath();
        ctx.arc(0, -h / 2 - 2, 2.6, 0, Math.PI * 2);
        ctx.fill();
        break;
      }
    }
  }

  /* =====================================================================
     10. NGƯỜI ĐI BỘ (học sinh) — vẽ vector đơn giản thay emoji
     ===================================================================== */
  const pedestrian = { x: 0, y: 0, walking: false, t: 0 };
  function resetPedestrian() {
    // Đứng ở vỉa hè ngay trước vạch kẻ đường bên trái (vạch này băng qua ĐƯỜNG NGANG)
    pedestrian.x = geo.boxL - CROSSWALK_W / 2;
    pedestrian.y = geo.boxT - CROSSWALK_W - 12;
    pedestrian.walking = false;
    pedestrian.t = 0;
  }
  function drawPedestrian() {
    let x = pedestrian.x,
      y = pedestrian.y;
    if (pedestrian.walking) {
      pedestrian.t = Math.min(1, pedestrian.t + 0.01);
      const yStart = geo.boxT - CROSSWALK_W - 12,
        yEnd = geo.boxB + CROSSWALK_W + 12;
      x = geo.boxL - CROSSWALK_W / 2; // đi thẳng theo đúng giữa vạch kẻ, không lệch ngang
      y = yStart + (yEnd - yStart) * pedestrian.t;
    }
    ctx.save();
    ctx.translate(x, y);
    ctx.fillStyle = "rgba(0,0,0,0.3)";
    ctx.beginPath();
    ctx.ellipse(1, 2, 6, 3, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = "#3b82f6";
    roundRectPath(-4, -3, 8, 8, 2);
    ctx.fill(); // ba lô/thân
    ctx.fillStyle = "#ffd8a8";
    ctx.beginPath();
    ctx.arc(0, -6, 4.4, 0, Math.PI * 2);
    ctx.fill(); // đầu
    ctx.restore();
  }
  window._simWalkPedestrian = function () {
    pedestrian.walking = true;
    pedestrian.t = 0;
  };
  window._simResetPedestrian = resetPedestrian;

  /* =====================================================================
     11. VÒNG LẶP CHÍNH
     ===================================================================== */
  let lastShownSignal = null;
  function tick(ts) {
    if (!lastTs) lastTs = ts;
    let dt = (ts - lastTs) / 1000;
    dt = Math.min(dt, 0.05); // tránh nhảy khung hình lớn khi tab bị ẩn
    lastTs = ts;

    if (running) {
      updateAxisSignal(dt);
      updateSpawners(dt);
      ambulanceBlink = (ambulanceBlink + dt * 2.2) % 1;
      vehicles.forEach((v) => updateVehicle(v, dt));
      if (
        intersectionLock !== null &&
        !vehicles.some((v) => v.id === intersectionLock && v.state !== "exit")
      ) {
        intersectionLock = null; // xe giữ khoá đã biến mất bất thường -> nhả khoá để tránh kẹt vĩnh viễn
      }
      vehicles = vehicles.filter((v) => v.state !== "exit");
    }

    // Đồng bộ ô đèn tín hiệu người đi bộ (HTML) với đèn xe thật của mô phỏng theo thời gian thực
    const liveSignal = getPedestrianSignal();
    if (liveSignal !== lastShownSignal) {
      setSignal(liveSignal);
      lastShownSignal = liveSignal;
    }

    drawEnvironment();
    vehicles.forEach(drawVehicle);
    drawPedestrian();

    requestAnimationFrame(tick);
  }

  /* =====================================================================
     12. API CÔNG KHAI cho phần game (scenario / scoring) gọi vào
     ===================================================================== */
  window.SimEngine = {
    setWeather(key) {
      weatherKey = key;
    },
    setVehicleBias(types) {
      vehicleBias = types;
    },
    getPedestrianSignal,
    newRound() {
      vehicles = [];
      spawnTimers = {};
      intersectionLock = null;
      resetPedestrian();
    },
  };

  resizeCanvas();
  resetPedestrian();
  requestAnimationFrame(tick);

  /* =====================================================================
     13. PHẦN GAME: kịch bản, tín hiệu người đi bộ, chấm điểm — GIỮ NGUYÊN
     logic như bản cũ, chỉ đổi cách "vẽ xe" (buildVehicles) sang gọi API
     của SimEngine ở trên thay vì tạo thẻ <div> emoji.
     ===================================================================== */
  const weatherOptions = [
    { key: "sunny", label: "☀️ Trời nắng", boardClass: "" },
    { key: "rain", label: "🌧️ Trời mưa", boardClass: "rain" },
    { key: "night", label: "🌙 Ban đêm", boardClass: "night" },
    { key: "fog", label: "🌫️ Sương mù", boardClass: "fog" },
  ];
  const vehicleSets = [
    ["bus", "car", "bicycle"],
    ["ambulance", "car", "motorbike"],
    ["car", "bus", "truck"],
    ["bicycle", "motorbike", "car"],
    ["bus", "ambulance", "bicycle"],
  ];
  const vehicleSetLabels = {
    bus: "🚌",
    car: "🚗",
    bicycle: "🚲",
    ambulance: "🚑",
    motorbike: "🏍️",
    truck: "🚚",
  };
  const signalStates = ["red", "yellow", "green"];
  const scenarioTexts = {
    red: "Đèn tín hiệu dành cho người đi bộ đang đỏ. Có xe cộ qua lại liên tục. Con đang đứng ở vỉa hè trước ngã tư.",
    yellow:
      "Đèn vừa chuyển sang vàng, các phương tiện đang chuẩn bị dừng. Con đang đứng gần vạch qua đường.",
    green:
      "Đèn tín hiệu dành cho người đi bộ đang xanh. Con đang chuẩn bị qua đường ở vạch kẻ.",
  };
  const correctActions = { red: "dung", yellow: "quansat", green: "qua" };
  const explain = {
    red: "Đèn đỏ nghĩa là chưa được qua đường. Con nên dừng lại ở vỉa hè và đợi đèn chuyển xanh.",
    yellow:
      "Đèn vàng là lúc cần quan sát kỹ hai bên trước khi quyết định — chưa nên bước xuống lòng đường vội.",
    green:
      "Đèn xanh cho người đi bộ nghĩa là an toàn để qua đường, nhưng vẫn nên đi trong vạch kẻ và quan sát thêm nhé.",
  };

  let round = 1,
    score = 0,
    current = {};

  function pick(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
  }

  function setSignal(state) {
    document.getElementById("sigRed").classList.toggle("on", state === "red");
    document
      .getElementById("sigYellow")
      .classList.toggle("on", state === "yellow");
    document
      .getElementById("sigGreen")
      .classList.toggle("on", state === "green");
  }

  function newScenario() {
    const weather = pick(weatherOptions);
    const vehicleTypes = pick(vehicleSets);
    current = { weather, vehicleTypes, answered: false };

    document.getElementById("board").className = "board " + weather.boardClass;
    SimEngine.setWeather(weather.key);
    SimEngine.setVehicleBias(vehicleTypes);
    SimEngine.newRound();
    document.getElementById("roundNum").textContent = round;
    document.getElementById("scenarioDesc").textContent =
      "Con đang đứng ở vạch kẻ đường. Đèn tín hiệu và xe cộ đang hoạt động thật ngay trong mô phỏng — hãy quan sát kỹ rồi mới quyết định nhé!";

    document.getElementById("scenarioList").innerHTML = `
      <li>🚦 Tín hiệu: <b style="color:#fff; margin-left:4px;">đang chạy thật, quan sát trực tiếp trên ngã tư</b></li>
      <li>${weather.label}</li>
      <li>🚗 Phương tiện: ${vehicleTypes.map((t) => vehicleSetLabels[t]).join(" ")}</li>
    `;

    document.getElementById("aiCallout").textContent =
      "🤖 AI: Quan sát đèn tín hiệu và phương tiện trước khi quyết định nhé.";
    const box = document.getElementById("feedbackBox");
    box.className = "feedback-box empty";
    box.innerHTML = "Chọn một hành động để xem AI chấm điểm và giải thích.";
    document.getElementById("fbActions").style.display = "none";
    document
      .querySelectorAll(".action-btn")
      .forEach((b) => (b.disabled = false));
  }

  function addHistory(isRight, pts, signalUsed) {
    const row = document.getElementById("historyRow");
    if (
      row.children.length === 1 &&
      row.children[0].textContent.includes("Chưa có")
    )
      row.innerHTML = "";
    const item = document.createElement("div");
    item.className = "history-item";
    item.innerHTML = `<span class="h-dot ${isRight ? "ok" : "bad"}"></span><span class="h-txt">Tình huống #${round} — ${signalUsed === "red" ? "Đèn đỏ" : signalUsed === "yellow" ? "Đèn vàng" : "Đèn xanh"}</span><span class="h-pt">+${pts}</span>`;
    row.prepend(item);
  }

  window.chooseAction = function (action) {
    if (current.answered) return;
    current.answered = true;
    document
      .querySelectorAll(".action-btn")
      .forEach((b) => (b.disabled = true));

    // Đánh giá theo đúng tín hiệu THẬT tại vạch kẻ đường ngay lúc bấm (không phải giá trị cố định trước đó)
    const liveSignal = SimEngine.getPedestrianSignal();
    const correct = correctActions[liveSignal];
    const isRight = action === correct;
    const pts = isRight ? 10 : 0;
    score += pts;
    document.getElementById("scoreVal").textContent = score;

    if (isRight && (action === "qua" || action === "di"))
      window._simWalkPedestrian();

    const box = document.getElementById("feedbackBox");
    box.className = "feedback-box";
    box.innerHTML = `
      <div class="fb-result ${isRight ? "ok" : "bad"}">${isRight ? "✅ Chính xác! +10 điểm" : "❌ Chưa đúng, thử lại lần sau nhé"}</div>
      <div class="fb-text">${explain[liveSignal]}</div>
    `;
    document.getElementById("fbActions").style.display = "flex";
    document.getElementById("aiCallout").textContent = isRight
      ? "🤖 AI: Tuyệt vời! Con đã xử lý tình huống rất an toàn."
      : "🤖 AI: Không sao, mình cùng xem lại cách xử lý đúng nhé.";

    addHistory(isRight, pts, liveSignal);
  };

  window.replay = function () {
    document
      .querySelectorAll(".action-btn")
      .forEach((b) => (b.disabled = false));
    current.answered = false;
    window._simResetPedestrian();
    const box = document.getElementById("feedbackBox");
    box.className = "feedback-box empty";
    box.innerHTML = "Chọn một hành động để xem AI chấm điểm và giải thích.";
    document.getElementById("fbActions").style.display = "none";
  };

  window.nextRound = function () {
    round += 1;
    newScenario();
  };

  const row = document.getElementById("condRow");
  if (row) {
    row.innerHTML = `
      <div class="cond-chip">🎲 Tình huống ngẫu nhiên mỗi lượt</div>
      <div class="cond-chip">🌦️ 4 kiểu thời tiết</div>
      <div class="cond-chip">🚗 AI mô phỏng giao thông thật</div>
      <div class="cond-chip">🤖 AI chấm điểm tức thì</div>
    `;
  }

  newScenario();
});
