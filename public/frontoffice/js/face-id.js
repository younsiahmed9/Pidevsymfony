(function () {
  "use strict";

  const openCvScriptUrl = "https://docs.opencv.org/4.x/opencv.js";
  const cascadeFileUrl = "https://raw.githubusercontent.com/opencv/opencv/master/data/haarcascades/haarcascade_frontalface_default.xml";
  const cascadeFileName = "haarcascade_frontalface_default.xml";
  let openCvReadyPromise = null;
  let cascadeReadyPromise = null;

  function loadScriptOnce(src) {
    return new Promise(function (resolve, reject) {
      const existingScript = document.querySelector('script[data-opencv-loader="true"]');
      if (existingScript && window.cv && window.cv.Mat) {
        resolve();
        return;
      }

      if (window.cv && window.cv.Mat) {
        resolve();
        return;
      }

      const script = document.createElement("script");
      script.src = src;
      script.async = true;
      script.dataset.opencvLoader = "true";
      script.onload = function () {
        if (window.cv && window.cv.Mat) {
          resolve();
          return;
        }

        if (window.cv) {
          window.cv.onRuntimeInitialized = function () {
            resolve();
          };
          return;
        }

        reject(new Error("OpenCV library did not initialize."));
      };
      script.onerror = function () {
        reject(new Error("Unable to load OpenCV library."));
      };
      document.head.appendChild(script);
    });
  }

  async function ensureOpenCvReady() {
    if (!openCvReadyPromise) {
      openCvReadyPromise = loadScriptOnce(openCvScriptUrl);
    }

    await openCvReadyPromise;

    if (!cascadeReadyPromise) {
      cascadeReadyPromise = fetch(cascadeFileUrl)
        .then(function (response) {
          if (!response.ok) {
            throw new Error("Unable to download OpenCV cascade file.");
          }

          return response.arrayBuffer();
        })
        .then(function (buffer) {
          const data = new Uint8Array(buffer);

          try {
            cv.FS_readFile("/" + cascadeFileName);
            return;
          } catch (readError) {
            // Missing file, we create it below.
          }

          try {
            cv.FS_createDataFile("/", cascadeFileName, data, true, false, false);
          } catch (createError) {
            if (!String((createError && createError.message) || "").toLowerCase().includes("exists")) {
              throw createError;
            }
          }
        });
    }

    await cascadeReadyPromise;
  }

  function getFormStatus(form) {
    return form.querySelector("[data-face-id-status]");
  }

  function getSubmitButton(form) {
    return form.querySelector("[data-face-id-submit]");
  }

  function getVideoElement(form) {
    return form.querySelector("[data-face-id-video]");
  }

  function getHiddenInput(form) {
    return form.querySelector("[data-face-id-input]");
  }

  function setStatus(form, message, isError) {
    const statusElement = getFormStatus(form);
    if (!statusElement) {
      return;
    }

    statusElement.textContent = message;
    statusElement.classList.toggle("text-danger", !!isError);
    statusElement.classList.toggle("text-muted", !isError);
  }

  async function startCamera(videoElement) {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      throw new Error("Your browser does not support camera access.");
    }

    if (videoElement.srcObject) {
      return;
    }

    const stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: "user" },
      audio: false,
    });
    videoElement.srcObject = stream;
    await videoElement.play();
  }

  function stopCamera(videoElement) {
    const stream = videoElement.srcObject;
    if (stream && typeof stream.getTracks === "function") {
      stream.getTracks().forEach(function (track) {
        track.stop();
      });
    }

    videoElement.srcObject = null;
  }

  async function captureDescriptor(videoElement) {
    await ensureOpenCvReady();

    const canvas = document.createElement("canvas");
    canvas.width = videoElement.videoWidth || 640;
    canvas.height = videoElement.videoHeight || 480;
    const context = canvas.getContext("2d");
    if (!context) {
      throw new Error("Unable to initialize canvas for capture.");
    }

    context.drawImage(videoElement, 0, 0, canvas.width, canvas.height);

    let sourceMat = null;
    let grayMat = null;
    let faceVector = null;
    let classifier = null;
    let faces = null;
    let faceRoi = null;
    let resizedFace = null;

    try {
      sourceMat = cv.imread(canvas);
      grayMat = new cv.Mat();
      cv.cvtColor(sourceMat, grayMat, cv.COLOR_RGBA2GRAY, 0);

      classifier = new cv.CascadeClassifier();
      classifier.load(cascadeFileName);
      faces = new cv.RectVector();
      const minSize = new cv.Size(80, 80);
      const maxSize = new cv.Size();
      classifier.detectMultiScale(grayMat, faces, 1.1, 4, 0, minSize, maxSize);

      if (faces.size() === 0) {
        throw new Error("No face detected. Center your face and try again.");
      }

      let selectedFace = faces.get(0);
      for (let i = 1; i < faces.size(); i += 1) {
        const candidate = faces.get(i);
        if (candidate.width * candidate.height > selectedFace.width * selectedFace.height) {
          selectedFace = candidate;
        }
      }

      faceRoi = grayMat.roi(selectedFace);
      resizedFace = new cv.Mat();
      cv.resize(faceRoi, resizedFace, new cv.Size(64, 64), 0, 0, cv.INTER_AREA);
      cv.equalizeHist(resizedFace, resizedFace);

      const template = [];
      for (let index = 0; index < resizedFace.data.length; index += 1) {
        template.push(resizedFace.data[index] / 255);
      }

      if (template.length !== 4096) {
        throw new Error("Unexpected face template size. Please try again.");
      }

      // Normalize the descriptor to unit length for better cosine similarity
      let magnitude = 0.0;
      for (let i = 0; i < template.length; i++) {
        magnitude += template[i] * template[i];
      }
      magnitude = Math.sqrt(magnitude);
      if (magnitude > 0) {
        for (let i = 0; i < template.length; i++) {
          template[i] = template[i] / magnitude;
        }
      }

      faceVector = template;
    } finally {
      if (resizedFace) resizedFace.delete();
      if (faceRoi) faceRoi.delete();
      if (faces) faces.delete();
      if (classifier) classifier.delete();
      if (grayMat) grayMat.delete();
      if (sourceMat) sourceMat.delete();
    }

    if (!faceVector) {
      throw new Error("Unable to capture a valid face template.");
    }

    return faceVector;
  }

  function bindFaceIdForm(form) {
    if (form.dataset.faceIdBound === "1") {
      return;
    }

    const modalElement = form.closest(".modal");
    const videoElement = getVideoElement(form);
    const hiddenInput = getHiddenInput(form);
    const submitButton = getSubmitButton(form);

    if (!videoElement || !hiddenInput || !submitButton) {
      return;
    }

    form.dataset.faceIdBound = "1";

    if (modalElement) {
      modalElement.addEventListener("shown.bs.modal", function () {
        setStatus(form, "Allow camera access, then capture your face to continue.", false);
        startCamera(videoElement).catch(function (error) {
          setStatus(form, error.message, true);
        });
      });

      modalElement.addEventListener("hidden.bs.modal", function () {
        stopCamera(videoElement);
        hiddenInput.value = "";
        submitButton.disabled = false;
        form.dataset.faceIdSubmitting = "0";
      });
    } else {
      setStatus(form, "Allow camera access, then capture your face to continue.", false);
      startCamera(videoElement).catch(function (error) {
        setStatus(form, error.message, true);
      });
    }

    form.addEventListener("submit", async function (event) {
      if (form.dataset.faceIdSubmitting === "1") {
        return;
      }

      event.preventDefault();
      submitButton.disabled = true;
      setStatus(form, "Scanning face...", false);

      try {
        await startCamera(videoElement);
        const descriptor = await captureDescriptor(videoElement);
        hiddenInput.value = JSON.stringify(descriptor);
        form.dataset.faceIdSubmitting = "1";
        form.submit();
      } catch (error) {
        submitButton.disabled = false;
        setStatus(form, error.message || "Unable to scan face.", true);
      }
    });
  }

  function bindFaceIdCaptureModal(modalElement) {
    if (modalElement.dataset.faceIdCaptureBound === "1") {
      return;
    }

    const targetSelector = modalElement.getAttribute("data-face-id-target");
    const targetInput = targetSelector ? document.querySelector(targetSelector) : null;
    const videoElement = modalElement.querySelector("[data-face-id-video]");
    const captureButton = modalElement.querySelector("[data-face-id-capture]");
    const statusElement = modalElement.querySelector("[data-face-id-status]");

    if (!targetInput || !videoElement || !captureButton) {
      return;
    }

    const setModalStatus = function (message, isError) {
      if (!statusElement) {
        return;
      }

      statusElement.textContent = message;
      statusElement.classList.toggle("text-danger", !!isError);
      statusElement.classList.toggle("text-muted", !isError);
    };

    modalElement.dataset.faceIdCaptureBound = "1";

    modalElement.addEventListener("shown.bs.modal", function () {
      setModalStatus("Allow camera access, then capture your face.", false);
      startCamera(videoElement).catch(function (error) {
        setModalStatus(error.message, true);
      });
    });

    modalElement.addEventListener("hidden.bs.modal", function () {
      stopCamera(videoElement);
      captureButton.disabled = false;
    });

    captureButton.addEventListener("click", async function () {
      captureButton.disabled = true;
      setModalStatus("Capturing face template...", false);

      try {
        await startCamera(videoElement);
        const descriptor = await captureDescriptor(videoElement);

        targetInput.value = JSON.stringify(descriptor);
        
        setModalStatus("Face ID captured successfully.", false);
        document.dispatchEvent(new CustomEvent("faceid:capture-success"));

        if (window.bootstrap && window.bootstrap.Modal) {
          const modalInstance = window.bootstrap.Modal.getInstance(modalElement);
          if (modalInstance) {
            modalInstance.hide();
          }
        }
      } catch (error) {
        setModalStatus(error.message || "Unable to capture face.", true);
      } finally {
        captureButton.disabled = false;
      }
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-face-id-form]").forEach(bindFaceIdForm);
    document.querySelectorAll("[data-face-id-capture-modal]").forEach(bindFaceIdCaptureModal);
  });
})();